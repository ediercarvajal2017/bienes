<?php
// Genera un .xlsx de carga masiva de usuarios con identidades ÚNICAS por corrida
// (a diferencia de un fixture estático de valores fijos): SIGEBI no libera el
// documento/correo de un usuario solo porque quedó en la papelera --
// Usuario::findByDocumento() no filtra eliminado_en IS NULL-- así que reusar el mismo
// documento en cada corrida de la prueba lo trata como "ya existe" (modificado, no
// nuevo) en vez de crear un usuario activo de verdad.
//
// Uso: php generar_carga_masiva_usuarios.php <sufijo> <ruta_destino.xlsx>

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

[, $sufijo, $destino] = $argv;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->fromArray(['Documento', 'Nombres', 'Apellidos', 'Cargo', 'Correo', 'Rol'], null, 'A1');
// Fila válida: documento y correo nuevos en esta corrida.
$sheet->fromArray(["PWCM{$sufijo}1", 'PW-TEST Nombres', 'Apellidos Carga', 'Docente', "pw-test-carga-{$sufijo}@example.com", 'Docente'], null, 'A2');
// Fila inválida a propósito: correo sin arroba.
$sheet->fromArray(["PWCM{$sufijo}2", 'PW-TEST Nombres Invalida', 'Apellidos Carga', 'Docente', 'correo-invalido-sin-arroba', 'Docente'], null, 'A3');

(new Xlsx($spreadsheet))->save($destino);
