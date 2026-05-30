<?php

declare(strict_types=1);

namespace App\Search\Domain\Model;

final readonly class ParsedSearchQuery
{
    /**
     * @param string[] $colors    Colores extraídos (e.g. ["azul", "negro"])
     * @param string[] $genero    Género extraído (e.g. ["mujer"])
     * @param string[] $tallas    Tallas extraídas (e.g. ["M", "L"])
     * @param string[] $temporada Temporada extraída (e.g. ["invierno"])
     * @param string[] $estilo    Estilo extraído (e.g. ["casual", "urbano"])
     * @param string[] $uso       Uso extraído (e.g. ["outdoor"])
     * @param string[] $ajuste    Ajuste extraído (e.g. ["slim fit", "holgado"])
     */
    public function __construct(
        public string $expandedText,
        public array $colors = [],
        public ?string $brand = null,
        public array $genero = [],
        public array $tallas = [],
        public array $temporada = [],
        public array $estilo = [],
        public array $uso = [],
        public array $ajuste = [],
    ) {}

    /** @return array<string, mixed> */
    public function toFilterArray(): array
    {
        $filters = [];
        if (!empty($this->colors))    $filters['color']    = $this->colors;
        if ($this->brand !== null)    $filters['brand']    = $this->brand;
        if (!empty($this->genero))    $filters['genero']   = $this->genero;
        if (!empty($this->tallas))    $filters['talla']    = $this->tallas;
        if (!empty($this->temporada)) $filters['temporada'] = $this->temporada;
        if (!empty($this->estilo))    $filters['estilo']   = $this->estilo;
        if (!empty($this->uso))       $filters['uso']      = $this->uso;
        if (!empty($this->ajuste))    $filters['ajuste']   = $this->ajuste;
        return $filters;
    }
}
