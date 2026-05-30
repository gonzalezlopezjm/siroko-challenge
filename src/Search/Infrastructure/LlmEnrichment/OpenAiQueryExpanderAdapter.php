<?php

declare(strict_types=1);

namespace App\Search\Infrastructure\LlmEnrichment;

use App\Search\Domain\Model\ParsedSearchQuery;
use App\Search\Domain\Port\QueryExpanderPort;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class OpenAiQueryExpanderAdapter implements QueryExpanderPort
{
    private const SYSTEM_PROMPT = <<<'PROMPT'
Eres un motor de búsqueda semántica para una tienda de ropa deportiva.
Analiza la búsqueda del usuario y responde SOLO con JSON válido.

TAREA PRINCIPAL — campo "expanded":
Escribe un perfil de producto de 5-6 frases RICAS EN VOCABULARIO TÉCNICO ESPECÍFICO como si fuera una ficha técnica de catálogo.
Identifica el TIPO DE PRENDA correcto a partir del contexto e inclúyelo con todos sus sinónimos:
  • Mojarse/lluvia/agua en bici → chaqueta cortavientos / chaleco ciclismo cortavientos / DWR repelente al agua / membrana impermeable / lluvia ciclismo
  • Pedalear/bicicleta/horas → culote/culotte/bib shorts / maillot/jersey / ropa ciclismo / acolchado badana Strato+ / tirantes / Breathlock+
  • Frío/invierno/bajas temperaturas en bici → maillot térmico manga larga / culote largo térmico cortavientos / guantes ciclismo invierno neopreno fleece / Thermoroubaix / equipación ciclismo invierno
  • Gafas/sol/vista → gafas deportivas ciclismo / lentes intercambiables / UV400 / fotocromático / policarbonato / DrySky
  • Hidratación/trail/montaña → chaleco hidratación trail running / mochila trail / reserva agua 5L / bastones / ultra trail / mountain running
  • Gym/fitness/entrenar/ejercicio → leggings/mallas fitness / camiseta técnica running / sujetador deportivo sports bra / shorts running / 4-way stretch / compresión media / licra lycra / cinturilla alta / secado rápido gym / entrenamiento fuerza cardio yoga HIIT
  • Sudadera/after sport/casual deportivo → sudadera hoodie / crew neck / algodón orgánico / felpa interior / post-entrenamiento / athleisure / lifestyle / uso diario casual
  • Lifestyle/urbano/día a día → camiseta algodón orgánico / sudadera / gorra snapback / streetwear / casual deportivo / everyday
  • MTB/mountain bike → maillot MTB / casco trail MIPS / gafas deportivas MTB / zapatillas MTB SPD Vibram
  • Trail running → chaleco hidratación / zapatillas trail / casco MTB MIPS / gafas fotocromáticas / ropa técnica trail

Incluye también en el texto:
- Materiales específicos: poliéster, elastán, nylon, merino, DWR, Thermoroubaix, neopreno, tejido MITI, algodón orgánico, lycra, 4-way stretch...
- Tecnologías: Breathlock+, MIPS, BOA, Elastic Interface, Strato+, UPF 50, Vibram, Lunarcell+, DrySky...
- Características técnicas: transpirable, térmico, cortavientos, impermeable, acolchado, bolsillos traseros, tirantes, alta compresión, cinturilla alta, secado rápido, antibacteriano, reflectante...
- Sinónimos que usaría un usuario (bib shorts=culote=culotte; jersey=maillot; bici=bicicleta; mallas=leggings=tights)

FILTROS — solo extraer cuando el usuario lo mencione explícitamente:

Reglas estrictas para filtros:
• "color": SOLO si el usuario dice un color concreto (azul, negro, rojo...). NO inferir.
• "brand": SIEMPRE null. Solo si el usuario dice "Siroko" literalmente. NO inferir por contexto.
• "genero": ["mujer"] si el usuario dice "mujer/femenino" O el producto es claramente femenino (sujetador/bra deportivo). ["hombre"] si dice "hombre/masculino". VACÍO en cualquier otro caso.
• "tallas": solo si el usuario menciona talla concreta (M, L, 42...). NO inferir.
• "temporada": SOLO si el usuario menciona temperatura o estación explícitamente. Valores: "invierno", "verano", "otoño", "primavera". Mapeos: "frío"/"frío extremo"/"bajas temperaturas" → "invierno"; "calor"/"caluroso" → "verano". NO inferir por tipo de producto.
• "estilo": SOLO si el usuario dice exactamente "casual", "urbano", "streetwear". NO inferir.
• "uso": SOLO si el usuario dice exactamente "post-entrenamiento", "casual", "lifestyle". NO inferir.
• "ajuste": SOLO si el usuario dice literalmente "holgado", "suelto", "alta compresión".
  NUNCA inferir de palabras como: "cómoda", "confort", "suave", "comfy", tipo de prenda o deporte.
  Ejemplos correctos: "camiseta holgada" → ["holgado"]; "culote alta compresión" → ["alta compresión"].
  Ejemplos INCORRECTOS: "sudadera cómoda" → []; "camiseta de gym" → []; "ropa cómoda" → [].

Responde con este JSON exacto:
{
  "expanded": "...",
  "filters": {
    "color": [],
    "brand": null,
    "genero": [],
    "tallas": [],
    "temporada": [],
    "estilo": [],
    "uso": [],
    "ajuste": []
  }
}
PROMPT;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $modelName,
    ) {}

    public function expand(string $query): ParsedSearchQuery
    {
        $response = $this->httpClient->request('POST', 'https://api.openai.com/v1/chat/completions', [
            'json' => [
                'model'           => $this->modelName,
                'messages'        => [
                    ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
                    ['role' => 'user', 'content' => $query],
                ],
                'response_format' => ['type' => 'json_object'],
                'temperature'     => 0,
                'max_tokens'      => 500,
            ],
        ]);

        $data    = json_decode($response->toArray()['choices'][0]['message']['content'], true);
        $filters = $data['filters'] ?? [];

        return new ParsedSearchQuery(
            expandedText: trim($data['expanded'] ?? $query),
            colors:       $this->toStringArray($filters['color'] ?? []),
            brand:        isset($filters['brand']) && is_string($filters['brand']) ? $filters['brand'] : null,
            genero:       $this->toStringArray($filters['genero'] ?? []),
            tallas:       $this->toStringArray($filters['tallas'] ?? []),
            temporada:    $this->toStringArray($filters['temporada'] ?? []),
            estilo:       $this->toStringArray($filters['estilo'] ?? []),
            uso:          $this->toStringArray($filters['uso'] ?? []),
            ajuste:       $this->toStringArray($filters['ajuste'] ?? []),
        );
    }

    /** @return string[] */
    private function toStringArray(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }
        return array_values(array_filter(array_map('strval', $value)));
    }
}
