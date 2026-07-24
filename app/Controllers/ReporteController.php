<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Services\ReporteService;

final class ReporteController
{
    public function index(): void
    {
        View::layout('partials/layout', 'reportes/index', [
            'title' => 'Reportes',
        ]);
    }

    public function carteraXlsx(): void
    {
        ReporteService::enviarXlsx(ReporteService::carteraBienes($this->institucionAExportar()), 'cartera_bienes');
    }

    public function carteraCsv(): void
    {
        ReporteService::enviarCsv(ReporteService::carteraBienes($this->institucionAExportar()), 'cartera_bienes');
    }

    public function reintegrosXlsx(): void
    {
        ReporteService::enviarXlsx(ReporteService::planillaReintegros($this->institucionAExportar()), 'planilla_reintegros');
    }

    public function reintegrosHistorialXlsx(): void
    {
        ReporteService::enviarXlsx(ReporteService::historialReintegros($this->institucionAExportar()), 'historial_reintegros');
    }

    private function institucionAExportar(): ?int
    {
        if (Auth::esSuperusuario()) {
            $solicitada = $_GET['institucion'] ?? '';

            return $solicitada !== '' ? (int) $solicitada : null;
        }

        return Auth::institucionId();
    }
}
