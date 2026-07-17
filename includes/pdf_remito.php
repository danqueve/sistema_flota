<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/datos_empresa.php';
require_once __DIR__ . '/funciones.php';

/**
 * Arma el HTML del remito replicando el talonario real
 * (docs/remito-actual.jpg.jpeg) y devuelve el PDF ya renderizado.
 *
 * $remito trae las columnas de `remitos` + `clientes.razon_social` y `clientes.cuit`.
 * $movimiento trae las columnas de `pallets_movimientos`.
 */
function generarPdfRemito(array $remito, array $movimiento): string
{
    $numeroFormateado = str_pad((string) $remito['numero'], 6, '0', STR_PAD_LEFT);
    $totalTarimas     = (int) $movimiento['sanos'] + (int) $movimiento['rotos'] + (int) $movimiento['reacondicionados'];

    $detalleTarimas = sprintf(
        '%d sanas, %d rotas, %d reacondicionadas',
        $movimiento['sanos'],
        $movimiento['rotos'],
        $movimiento['reacondicionados']
    );

    $filaTarimas      = $remito['tipo'] === 'recepcion' ? $totalTarimas . ' (' . $detalleTarimas . ')' : '';
    $filaDevoluciones = $remito['tipo'] === 'devolucion' ? $totalTarimas . ' (' . $detalleTarimas . ')' : '';

    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>' . estilosPdfRemito() . '</style></head><body>';
    $html .= '<div class="marco">';
    $html .= '<div class="disclaimer">X DOCUMENTO NO VÁLIDO COMO FACTURA</div>';

    $html .= '<table class="cabecera"><tr>';
    $html .= '<td class="col-marca">';
    $html .= '<div class="marca">' . htmlspecialchars(EMPRESA_MARCA) . '</div>';
    $html .= '<div class="razon-social">RAZÓN SOCIAL: ' . htmlspecialchars(EMPRESA_RAZON_SOCIAL) . '</div>';
    $html .= '<div class="datos-contacto">' . htmlspecialchars(EMPRESA_DOMICILIO) . '<br>' . htmlspecialchars(EMPRESA_TELEFONOS) . '</div>';
    $html .= '</td>';
    $html .= '<td class="col-comprobante">';
    $html .= '<div class="tipo-comprobante">COMPROBANTE INTERNO</div>';
    $html .= '<div class="remito">REMITO</div>';
    $html .= '<div class="numero">N&deg; ' . $numeroFormateado . '</div>';
    $html .= '<div>FECHA ' . htmlspecialchars(formatearFecha($remito['fecha'])) . '</div>';
    $html .= '<div class="iva">IVA: ' . htmlspecialchars(EMPRESA_CONDICION_IVA) . ' - CUIT N&deg;: ' . htmlspecialchars(EMPRESA_CUIT) . '</div>';
    $html .= '</td>';
    $html .= '</tr></table>';

    $html .= '<div class="linea-datos">Recibí del Transporte: ' . htmlspecialchars($remito['transporte_origen'] ?: '—')
        . ' &nbsp;&nbsp;&nbsp; CUIT: ' . htmlspecialchars($remito['transporte_cuit'] ?: '—') . '</div>';
    $html .= '<div class="linea-datos">Chofer: ' . htmlspecialchars($remito['chofer_nombre'] ?: '—')
        . ' &nbsp;&nbsp;&nbsp; DNI: ' . htmlspecialchars($remito['chofer_dni'] ?: '—') . '</div>';
    $html .= '<div class="linea-datos">Hoja de ruta: ' . htmlspecialchars($remito['hoja_ruta'] ?: '—') . '</div>';
    $html .= '<div class="linea-datos">Cliente: ' . htmlspecialchars($remito['razon_social'])
        . ' &nbsp;&nbsp;&nbsp; CUIT: ' . htmlspecialchars($remito['cliente_cuit'] ?: '—') . '</div>';

    $html .= '<table class="conceptos">';
    $html .= '<tr><td class="concepto">Documentación</td><td>' . htmlspecialchars($remito['documentacion'] ?: '') . '</td></tr>';
    $html .= '<tr><td class="concepto">Tarimas</td><td>' . htmlspecialchars($filaTarimas) . '</td></tr>';
    $html .= '<tr><td class="concepto">Separadores</td><td>' . ((int) $movimiento['separadores'] > 0 ? (int) $movimiento['separadores'] : '') . '</td></tr>';
    $html .= '<tr><td class="concepto">Peajes</td><td>' . htmlspecialchars($remito['peajes'] ?: '') . '</td></tr>';
    $html .= '<tr><td class="concepto">Devoluciones</td><td>' . htmlspecialchars($filaDevoluciones) . '</td></tr>';
    $html .= '<tr><td class="concepto">Observaciones</td><td>' . htmlspecialchars($movimiento['observaciones'] ?: '') . '</td></tr>';
    $html .= '<tr><td class="concepto">&nbsp;</td><td>&nbsp;</td></tr>';
    $html .= '</table>';

    $html .= '<div class="pie-empresa">';
    $html .= htmlspecialchars(EMPRESA_MARCA) . '<br>';
    $html .= 'CUIT. ' . htmlspecialchars(EMPRESA_CUIT) . '<br>';
    $html .= htmlspecialchars(EMPRESA_RAZON_SOCIAL);
    $html .= '</div>';

    $html .= '<table class="firmas"><tr><td>Firma del Recepcionista</td><td>Firma del Chofer</td></tr></table>';
    $html .= '</div></body></html>';

    $dompdf = new \Dompdf\Dompdf(['isRemoteEnabled' => false]);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    return $dompdf->output();
}

function rutaPdfRemito(int $numero): string
{
    $numeroFormateado = str_pad((string) $numero, 6, '0', STR_PAD_LEFT);

    return rtrim(ARCHIVOS_DIR, '/') . '/remitos/remito_' . $numeroFormateado . '.pdf';
}

/**
 * Genera (o regenera) el PDF de un remito ya guardado y lo escribe en
 * ARCHIVOS_DIR/remitos/. Devuelve la ruta del archivo. Marca
 * remitos.pdf_generado=1 la primera vez.
 */
function generarYGuardarPdfRemito(PDO $pdo, int $remitoId): string
{
    $stmt = $pdo->prepare(
        'SELECT r.*, cl.razon_social, cl.cuit AS cliente_cuit
         FROM remitos r
         JOIN clientes cl ON cl.id = r.cliente_id
         WHERE r.id = ?'
    );
    $stmt->execute([$remitoId]);
    $remito = $stmt->fetch();

    if (!$remito) {
        throw new RuntimeException('No encontré el remito #' . $remitoId . ' para generar el PDF.');
    }

    $stmt = $pdo->prepare('SELECT * FROM pallets_movimientos WHERE remito_id = ?');
    $stmt->execute([$remitoId]);
    $movimiento = $stmt->fetch();

    if (!$movimiento) {
        throw new RuntimeException('No encontré el movimiento del remito #' . $remitoId . '.');
    }

    $pdfBinario = generarPdfRemito($remito, $movimiento);

    $directorio = rtrim(ARCHIVOS_DIR, '/') . '/remitos';
    if (!is_dir($directorio) && !mkdir($directorio, 0750, true) && !is_dir($directorio)) {
        throw new RuntimeException('No pude crear la carpeta de remitos.');
    }

    $ruta = rutaPdfRemito((int) $remito['numero']);
    file_put_contents($ruta, $pdfBinario);

    $pdo->prepare('UPDATE remitos SET pdf_generado = 1 WHERE id = ?')->execute([$remitoId]);

    return $ruta;
}

function estilosPdfRemito(): string
{
    return '
        @page { margin: 20px 26px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #000; }
        .marco { border: 1.5px solid #000; padding: 12px 16px; }
        .disclaimer { text-align:center; font-size:9px; font-weight:bold; letter-spacing:.05em;
          border-bottom:1px solid #000; padding-bottom:8px; margin-bottom:10px; }
        table.cabecera { width:100%; border-collapse:collapse; }
        table.cabecera td { vertical-align:top; }
        .col-comprobante { text-align:right; width:40%; }
        .marca { font-size:20px; font-weight:bold; }
        .razon-social { font-size:9px; margin-top:2px; }
        .datos-contacto { font-size:9px; margin-top:4px; line-height:1.5; }
        .tipo-comprobante { font-size:9px; font-weight:bold; }
        .remito { font-size:15px; font-weight:bold; margin-top:2px; }
        .numero { font-size:20px; font-weight:bold; margin-top:2px; }
        .iva { font-size:8px; margin-top:4px; }
        .linea-datos { margin-top:9px; font-size:11px; border-bottom:1px dotted #999; padding-bottom:3px; }
        table.conceptos { width:100%; border-collapse:collapse; margin-top:12px; }
        table.conceptos td { border:1px solid #000; padding:7px 9px; font-size:11px; height:20px; }
        table.conceptos td.concepto { font-weight:bold; width:32%; }
        .pie-empresa { margin-top:18px; font-size:9px; line-height:1.5; }
        table.firmas { width:100%; margin-top:36px; }
        table.firmas td { width:50%; text-align:center; border-top:1px solid #000; padding-top:5px; font-size:9px; }
    ';
}
