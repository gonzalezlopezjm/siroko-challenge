<?php

declare(strict_types=1);

namespace App\Search\Infrastructure\LlmEnrichment;

use App\Search\Domain\Port\SemanticEnrichmentPort;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class OpenAiSemanticEnrichmentAdapter implements SemanticEnrichmentPort
{
    private const SYSTEM_PROMPT = 'Eres un experto en búsqueda semántica para una tienda de ropa deportiva (Siroko). Para el siguiente producto, genera un texto de 4-5 párrafos que contenga: (1) descripción técnica del producto con materiales, tecnologías y características específicas; (2) sinónimos y nombres alternativos de la prenda (maillot/jersey/camiseta ciclismo, culote/culotte/bib shorts, gafas/lentes deportivas, camiseta técnica/running/fitness, leggings/mallas, sudadera/hoodie, etc.); (3) actividades deportivas y contextos de uso específicos; (4) en el último párrafo, incluye LITERALMENTE frases de búsqueda de usuario del estilo: "Si buscas [frase_búsqueda_usuario_1], [frase_búsqueda_usuario_2] o [frase_búsqueda_usuario_3], este es tu producto." Usa frases concretas como: "camiseta azul para correr", "camiseta holgada para entrenar", "ropa para ir al gimnasio", "mallas de gym para mujer", "camiseta básica mujer para el día a día", "ropa de ciclismo naranja", "sudadera cómoda para después de entrenar", "equipación para mountain bike", "ropa técnica para trail running", "gran fondo de ciclismo", etc. — elige las más relevantes para el producto; (5) términos de temporada, ajuste y rendimiento. Responde SOLO con el texto, sin explicaciones ni formato.';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $modelName,
    ) {}

    public function enrich(
        string $name,
        string $description,
        string $category,
        string $brand,
        int $priceAmount,
        string $priceCurrency,
        array $attributes,
    ): string {
        $price = number_format($priceAmount / 100, 2, ',', '.') . ' ' . $priceCurrency;

        $lines = [
            "Nombre: {$name}",
            "Marca: {$brand}",
            "Categoría: {$category}",
            "Precio: {$price}",
        ];

        if ($description !== '') {
            $lines[] = "Descripción: {$description}";
        }

        foreach ($attributes as $key => $value) {
            $flat = is_array($value) ? implode(', ', $value) : (string) $value;
            if ($flat !== '') {
                $lines[] = ucfirst($key) . ': ' . $flat;
            }
        }

        $response = $this->httpClient->request('POST', 'https://api.openai.com/v1/chat/completions', [
            'json' => [
                'model'       => $this->modelName,
                'messages'    => [
                    ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
                    ['role' => 'user', 'content' => implode("\n", $lines)],
                ],
                'temperature' => 0,
                'max_tokens'  => 500,
            ],
        ]);

        return trim($response->toArray()['choices'][0]['message']['content']);
    }
}
