<?php

use Dompdf\Dompdf;
use Dompdf\Options;

// Arma un PDF simple (tablas HTML renderizadas con dompdf) para cada reporte.
// Reutiliza los mismos controllers que ya alimentan al frontend (Campo/Lote/
// Insumo) en vez de duplicar las consultas SQL.
class ReporteController
{
    public static function pdfCamposLotes(int $tenantId): string
    {
        $campos = CampoController::listar($tenantId);
        $lotes = LoteController::listar($tenantId);

        $lotesPorCampo = [];
        foreach ($lotes as $lote) {
            $lotesPorCampo[$lote['campo_id']][] = $lote;
        }

        $html = '<h1>Reporte de campos y lotes</h1>';
        $html .= '<p class="fecha">Generado el ' . date('d/m/Y H:i') . '</p>';

        if (empty($campos)) {
            $html .= '<p><em>Todavía no hay campos cargados.</em></p>';
        }

        foreach ($campos as $campo) {
            $html .= '<h2>' . htmlspecialchars($campo['nombre']) . '</h2>';
            if (!empty($campo['ubicacion'])) {
                $html .= '<p>Ubicación: ' . htmlspecialchars($campo['ubicacion']) . '</p>';
            }

            $lotesDelCampo = $lotesPorCampo[$campo['id']] ?? [];
            if (empty($lotesDelCampo)) {
                $html .= '<p><em>Sin lotes cargados.</em></p>';
                continue;
            }

            $html .= '<table><tr><th>Lote</th><th>Hectáreas</th><th>Perímetro (m)</th></tr>';
            $totalHectareas = 0.0;
            foreach ($lotesDelCampo as $lote) {
                $totalHectareas += (float) $lote['hectareas'];
                $html .= '<tr><td>' . htmlspecialchars($lote['nombre']) . '</td>'
                    . '<td>' . htmlspecialchars((string) $lote['hectareas']) . '</td>'
                    . '<td>' . htmlspecialchars((string) ($lote['perimetro_metros'] ?? '-')) . '</td></tr>';
            }
            $html .= '</table>';
            $html .= '<p class="total">Total: ' . round($totalHectareas, 2) . ' ha</p>';
        }

        return self::render($html);
    }

    public static function pdfInsumosStock(int $tenantId): string
    {
        $stock = InsumoController::stock($tenantId);

        $html = '<h1>Reporte de insumos y stock</h1>';
        $html .= '<p class="fecha">Generado el ' . date('d/m/Y H:i') . '</p>';

        if (empty($stock)) {
            $html .= '<p><em>Todavía no hay insumos cargados.</em></p>';
        } else {
            $html .= '<table><tr><th>Insumo</th><th>Marca</th><th>Categoría</th><th>Stock actual</th></tr>';
            foreach ($stock as $item) {
                $claseNegativo = (float) $item['stock_actual'] <= 0 ? ' class="negativo"' : '';
                $html .= '<tr><td>' . htmlspecialchars($item['nombre']) . '</td>'
                    . '<td>' . htmlspecialchars($item['marca_nombre'] ?? '-') . '</td>'
                    . '<td>' . htmlspecialchars($item['categoria_nombre'] ?? '-') . '</td>'
                    . '<td' . $claseNegativo . '>' . htmlspecialchars((string) $item['stock_actual']) . ' '
                    . htmlspecialchars($item['unidad_medida']) . '</td></tr>';
            }
            $html .= '</table>';
        }

        return self::render($html);
    }

    private static function render(string $cuerpoHtml): string
    {
        $html = '<html><head><meta charset="utf-8"><style>' . self::estilos() . '</style></head><body>'
            . $cuerpoHtml . '</body></html>';

        // isRemoteEnabled queda apagado a propósito: el PDF no debe depender de
        // que una URL externa (logo, fuente) responda en el momento de generarlo.
        $opciones = new Options();
        $opciones->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($opciones);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    private static function estilos(): string
    {
        return 'body { font-family: sans-serif; color: #1f2937; }'
            . 'h1 { color: #1f4b33; } h2 { color: #1f4b33; margin-top: 20px; }'
            . 'table { width: 100%; border-collapse: collapse; margin-top: 8px; }'
            . 'th, td { border: 1px solid #d1d5db; padding: 6px 8px; text-align: left; font-size: 12px; }'
            . 'th { background: #f3f4f6; }'
            . '.fecha { color: #6b7280; font-size: 11px; }'
            . '.total { font-weight: bold; }'
            . '.negativo { color: #b91c1c; font-weight: bold; }';
    }
}
