<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Aviso;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Core\Url;
use App\Core\View;
use App\Helpers\Paginador;
use App\Helpers\PoliticaContrasena;
use App\Helpers\Uploader;
use App\Models\Auditoria;
use App\Models\Cargo;
use App\Models\CodigoRecuperacion;
use App\Models\DispositivoConfiable;
use App\Models\Institucion;
use App\Models\Usuario;
use App\Services\DosFactoresService;

final class UsuarioController
{
    private const POR_PAGINA_DEFECTO = 25;
    private const OPCIONES_POR_PAGINA = [10, 25, 50, 100, 0];

    public function index(): void
    {
        $institucionId = Auth::esSuperusuario() ? Auth::filtroInstitucionId() : Auth::institucionId();
        $busqueda = trim((string) ($_GET['q'] ?? ''));
        $terminoBusqueda = $busqueda !== '' ? $busqueda : null;
        $pagina = max(1, (int) ($_GET['pagina'] ?? 1));
        $porPagina = (int) ($_GET['porPagina'] ?? self::POR_PAGINA_DEFECTO);
        if (!in_array($porPagina, self::OPCIONES_POR_PAGINA, true)) {
            $porPagina = self::POR_PAGINA_DEFECTO;
        }

        $total = Usuario::contarListado($institucionId, $terminoBusqueda, Auth::esSuperusuario());

        View::layout('partials/layout', 'usuarios/index', [
            'title' => 'Usuarios',
            'usuarios' => Usuario::listar($institucionId, $terminoBusqueda, $pagina, $porPagina, Auth::esSuperusuario(), ...\App\Helpers\OrdenListado::desdeConsulta(Usuario::ORDENES)),
            'busqueda' => $busqueda,
            'pagina' => $pagina,
            'porPagina' => $porPagina,
            'opcionesPorPagina' => self::OPCIONES_POR_PAGINA,
            'total' => $total,
            'totalPaginas' => Paginador::totalPaginas($total, $porPagina),
        ]);
    }

    public function crear(): void
    {
        View::layout('partials/layout', 'usuarios/form', [
            'title' => 'Nuevo usuario',
            'usuario' => null,
            'cargos' => Cargo::activos(),
            'roles' => Usuario::rolesParaSelect(Auth::esSuperusuario()),
            'instituciones' => Auth::esSuperusuario() ? Institucion::listadoParaSelect(true) : [],
            'familiaSedes' => $this->familiaSedesDelRector(),
            'viejo' => Session::pullOld(),
        ]);
    }

    public function guardar(): void
    {
        $request = new Request();
        $datos = $this->datosDesdeFormulario($request);
        $this->verificarCsrf($request, '/usuarios/crear', $datos);

        $password = (string) $request->input('password');

        if ($falla = $this->validar($datos, $password, null, false)) {
            Aviso::enviar(Aviso::error('No se creó el usuario', $falla['porQue'], $falla['queHacer'], $falla['accion'] ?? null, [$falla['campo'] => $falla['enCampo']]));
            Session::flashOld($datos);
            header('Location: ' . Url::to('/usuarios/crear'));
            exit;
        }

        $snapshot = $datos;
        $datos['password_hash'] = password_hash($password, PASSWORD_BCRYPT);
        $id = Usuario::create($datos);
        Auditoria::registrar(Auth::id(), (int) $datos['institucion_id'], 'crear', 'usuario', $id, null, $snapshot);

        if ($archivo = $request->file('foto')) {
            $this->subirFoto($id, $archivo);
        }

        Aviso::enviar(Aviso::exito("Usuario {$datos['nombres']} {$datos['apellidos']} creado.",
            'Siguiente paso: la persona entra a MIA con su correo y la contraseña que le asignaste; puede cambiarla en «Mi cuenta».'));
        header('Location: ' . Url::to('/usuarios'));
        exit;
    }

    public function editar(string $id): void
    {
        $usuario = Usuario::find((int) $id);
        $this->verificarAcceso($usuario);

        View::layout('partials/layout', 'usuarios/form', [
            'title' => 'Editar usuario',
            'usuario' => $usuario,
            'cargos' => $this->cargosParaFormulario($usuario),
            'roles' => Usuario::rolesParaSelect(Auth::esSuperusuario()),
            'instituciones' => Auth::esSuperusuario() ? $this->institucionesParaFormulario($usuario) : [],
            'familiaSedes' => $this->familiaSedesDelRector(),
            'dosFactoresActiva' => DosFactoresService::tieneActiva($usuario),
            'viejo' => Session::pullOld(),
        ]);
    }

    /**
     * Para quien perdió el teléfono: quita su verificación en dos pasos, sus códigos de
     * recuperación y sus dispositivos de confianza, y cierra sus sesiones. En su próximo
     * ingreso entra con la contraseña y, si su rol la exige, la configura de nuevo.
     * Se pide la contraseña de quien lo hace (una sesión abierta y olvidada no basta).
     */
    public function restablecerDosFactores(string $id): void
    {
        $id = (int) $id;
        $usuario = Usuario::find($id);
        $this->verificarAcceso($usuario);

        $request = new Request();
        $this->verificarCsrf($request, "/usuarios/{$id}/editar");

        if ($id === Auth::id()) {
            $this->volverAlUsuario($id, Aviso::error('No se restableció la verificación en dos pasos', 'Tu propia verificación en dos pasos no se restablece desde aquí.',
                'Adminístrala desde «Mi cuenta».', Aviso::accion('Ir a Mi cuenta', '/mi-cuenta')));
        }

        $administrador = Usuario::findParaAcceso((int) Auth::id());
        if ($administrador === null || !password_verify((string) $request->input('password_confirmacion'), (string) $administrador['password_hash'])) {
            $this->volverAlUsuario($id, Aviso::error('No se restableció la verificación en dos pasos', 'Tu contraseña no es correcta.',
                'Escribe tu propia contraseña (la de tu cuenta, no la del usuario) y vuelve a intentarlo.'));
        }

        if (!DosFactoresService::tieneActiva($usuario)) {
            $this->volverAlUsuario($id, Aviso::error('No se restableció la verificación en dos pasos', 'Este usuario no tiene la verificación en dos pasos activa.',
                'No hace falta hacer nada: ya entra solo con su contraseña.'));
        }

        Usuario::desactivarTotp($id);
        CodigoRecuperacion::borrarDe($id);
        DispositivoConfiable::revocarTodosDe($id);
        Usuario::invalidarSesiones($id);

        Auditoria::registrar(Auth::id(), (int) $usuario['institucion_id'], '2fa_restablecer', 'usuario', $id);
        DosFactoresService::avisarPorCorreo($usuario, 'verificación en dos pasos restablecida',
            'Un administrador restableció la verificación en dos pasos de tu cuenta de MIA. En tu próximo ingreso podrás configurarla de nuevo.');

        $this->volverAlUsuario($id, Aviso::exito('Verificación en dos pasos restablecida.',
            'Sus sesiones abiertas se cerrarán en menos de un minuto; en su próximo ingreso podrá configurarla de nuevo.'));
    }

    /** @param array{tipo: string, titulo: string, detalle: string, solucion: string, accion: array{texto: string, url: string}|null, campos: array<string, string>} $aviso */
    private function volverAlUsuario(int $id, array $aviso): never
    {
        Aviso::enviar($aviso);
        header('Location: ' . Url::to("/usuarios/{$id}/editar"));
        exit;
    }

    public function actualizar(string $id): void
    {
        $id = (int) $id;
        $usuario = Usuario::find($id);
        $this->verificarAcceso($usuario);

        $request = new Request();
        $datos = $this->datosDesdeFormulario($request);
        $this->verificarCsrf($request, "/usuarios/{$id}/editar", $datos);

        $password = (string) $request->input('password');

        if ($falla = $this->validar($datos, $password, $id, true)) {
            Aviso::enviar(Aviso::error('No se guardaron los cambios del usuario', $falla['porQue'], $falla['queHacer'], $falla['accion'] ?? null,
                [$falla['campo'] => $falla['enCampo']]));
            Session::flashOld($datos);
            header('Location: ' . Url::to("/usuarios/{$id}/editar"));
            exit;
        }

        $antes = $usuario;
        unset($antes['password_hash']);
        Usuario::update($id, $datos);
        Auditoria::registrar(Auth::id(), (int) $datos['institucion_id'], 'editar', 'usuario', $id, $antes, $datos);

        if ($password !== '') {
            Usuario::updatePassword($id, password_hash($password, PASSWORD_BCRYPT));
        }

        // Con otra contraseña, otro rol u otra institución, las sesiones abiertas de ese
        // usuario ya no corresponden a la cuenta: se cierran (si es el propio usuario, la
        // sesión actual sigue con la versión nueva).
        $cambioSensible = $password !== ''
            || (int) $datos['rol_id'] !== (int) $usuario['rol_id']
            || (int) $datos['institucion_id'] !== (int) $usuario['institucion_id'];
        if ($cambioSensible) {
            $version = Usuario::invalidarSesiones($id);
            if ($id === Auth::id()) {
                Auth::actualizarVersionSesion($version);
            }
        }

        if ($archivo = $request->file('foto')) {
            $this->subirFoto($id, $archivo);
        }

        Aviso::enviar(Aviso::exito("Cambios de {$datos['nombres']} {$datos['apellidos']} guardados."));
        header('Location: ' . Url::to('/usuarios'));
        exit;
    }

    public function cambiarEstado(string $id): void
    {
        $id = (int) $id;
        $usuario = Usuario::find($id);
        $this->verificarAcceso($usuario);

        if ($id === Auth::id()) {
            Aviso::enviar(Aviso::error('No se cambió el estado', 'No puedes desactivar tu propia cuenta: te quedarías sin acceso.',
                'Si de verdad hace falta, pídele a otro administrador que lo haga.'));
            header('Location: ' . Url::to('/usuarios'));
            exit;
        }

        $request = new Request();
        $this->verificarCsrf($request, '/usuarios');

        $activar = !((bool) $usuario['activo']);
        Usuario::setActivo($id, $activar);
        if (!$activar) {
            Usuario::invalidarSesiones($id); // sale del sistema en máximo un minuto
        }
        Auditoria::registrar(
            Auth::id(),
            (int) $usuario['institucion_id'],
            $activar ? 'activar' : 'desactivar',
            'usuario',
            $id,
            ['activo' => (int) $usuario['activo']],
            ['activo' => (int) $activar]
        );

        $nombre = trim($usuario['nombres'] . ' ' . $usuario['apellidos']);
        Aviso::enviar($activar
            ? Aviso::exito("Usuario {$nombre} activado.", 'Ya puede entrar a MIA con su correo y contraseña.')
            : Aviso::exito("Usuario {$nombre} desactivado.", 'Si tenía una sesión abierta, se cerrará en menos de un minuto. Sus registros se conservan.'));
        header('Location: ' . Url::to('/usuarios'));
        exit;
    }

    public function eliminar(string $id): void
    {
        $id = (int) $id;
        $usuario = Usuario::find($id);
        $this->verificarAcceso($usuario);

        if ($id === Auth::id()) {
            Aviso::enviar(Aviso::error('No se eliminó el usuario', 'No puedes eliminar tu propia cuenta.',
                'Si de verdad hace falta, pídele a otro administrador que lo haga.'));
            header('Location: ' . Url::to('/usuarios'));
            exit;
        }

        $request = new Request();
        $this->verificarCsrf($request, '/usuarios');

        if (Usuario::estaEnUso($id)) {
            Aviso::enviar(Aviso::error('No se eliminó el usuario', 'El usuario tiene movimientos o registros asociados que deben conservarse.',
                'Desactívalo en su lugar: no podrá entrar y su historial queda intacto.'));
        } else {
            $snapshot = $usuario;
            unset($snapshot['password_hash']);
            Usuario::eliminar($id, (int) Auth::id());
            Usuario::invalidarSesiones($id);
            Auditoria::registrar(Auth::id(), (int) $usuario['institucion_id'], 'eliminar', 'usuario', $id, $snapshot);
            Aviso::enviar(Aviso::exito('Usuario ' . trim($usuario['nombres'] . ' ' . $usuario['apellidos']) . ' enviado a la papelera.',
                'Si fue un error, un superusuario puede restaurarlo desde la Papelera.'));
        }

        header('Location: ' . Url::to('/usuarios'));
        exit;
    }

    private function subirFoto(int $usuarioId, array $archivo): void
    {
        try {
            $path = Uploader::storeImage($archivo, 'fotos_usuarios');
            if ($path) {
                Usuario::updateFoto($usuarioId, $path);
            }
        } catch (\RuntimeException $e) {
            // Los datos ya se guardaron: solo faltó la foto.
            Aviso::enviar(Aviso::advertencia('La foto no se guardó', $e->getMessage(),
                'Abre el usuario y vuelve a subir la foto (JPG o PNG).', Aviso::accion('Abrir el usuario', "/usuarios/{$usuarioId}/editar")));
        }
    }

    private function datosDesdeFormulario(Request $request): array
    {
        $institucionId = $this->institucionIdDesdeFormulario($request);

        return [
            'documento' => trim((string) $request->input('documento')),
            'nombres' => trim((string) $request->input('nombres')),
            'apellidos' => trim((string) $request->input('apellidos')),
            'cargo_id' => (int) $request->input('cargo_id'),
            'email' => trim((string) $request->input('email')),
            'institucion_id' => $institucionId,
            'rol_id' => (int) $request->input('rol_id'),
        ];
    }

    /**
     * El superusuario elige libremente. Un rector con más de una sede en su familia
     * también puede elegir, pero limitado a esa familia (nunca a una institución ajena) —
     * así puede crear o mover un usuario a cualquiera de sus sedes sin necesitar una
     * cuenta de superusuario. Cualquier otro caso (secretario, docente, o un valor que no
     * pertenece a la familia) se queda fijo en la sede activa, igual que antes.
     */
    private function institucionIdDesdeFormulario(Request $request): int
    {
        if (Auth::esSuperusuario()) {
            return (int) $request->input('institucion_id');
        }

        if (Auth::rol() === 'rector') {
            $solicitada = (int) $request->input('institucion_id');
            foreach ($this->familiaSedesDelRector() as $sede) {
                if ((int) $sede['id'] === $solicitada) {
                    return $solicitada;
                }
            }
        }

        return (int) Auth::institucionId();
    }

    /**
     * La familia (principal + secciones) de la sede activa del rector, o un arreglo
     * vacío para cualquier otro rol — usado tanto para armar el selector de sede en el
     * formulario como para validar lo que llegue en institucionIdDesdeFormulario().
     */
    private function familiaSedesDelRector(): array
    {
        if (Auth::rol() !== 'rector' || !Auth::institucionId()) {
            return [];
        }

        return Institucion::familiaDe((int) Auth::institucionId());
    }

    /**
     * Primera regla del formulario que no se cumple: el porqué, qué hacer, el campo a marcar
     * (name del control) y el mensaje corto debajo de él.
     *
     * @return array{campo: string, porQue: string, queHacer: string, enCampo: string, accion?: array{texto: string, url: string}}|null
     */
    private function validar(array $datos, string $password, ?int $exceptId, bool $esEdicion): ?array
    {
        if ($datos['documento'] === '') {
            return ['campo' => 'documento', 'porQue' => 'Falta el número de documento.', 'enCampo' => 'Escribe el documento.',
                'queHacer' => 'Escribe el documento sin puntos ni espacios y vuelve a guardar.'];
        }

        if ($datos['nombres'] === '') {
            return ['campo' => 'nombres', 'porQue' => 'Faltan los nombres.', 'enCampo' => 'Escribe los nombres.',
                'queHacer' => 'Escribe los nombres de la persona y vuelve a guardar.'];
        }

        if ($datos['apellidos'] === '') {
            return ['campo' => 'apellidos', 'porQue' => 'Faltan los apellidos.', 'enCampo' => 'Escribe los apellidos.',
                'queHacer' => 'Escribe los apellidos de la persona y vuelve a guardar.'];
        }

        if ($datos['email'] === '') {
            return ['campo' => 'email', 'porQue' => 'Falta el correo.', 'enCampo' => 'Escribe el correo.',
                'queHacer' => 'Escribe el correo con el que la persona entrará a MIA y vuelve a guardar.'];
        }

        // Lo que cabe en la base (usuarios): un texto más largo se rechaza con su motivo.
        $largos = [
            'documento' => ['El documento es más largo de lo que se puede guardar.', 20],
            'nombres' => ['Los nombres son más largos de lo que se puede guardar.', 100],
            'apellidos' => ['Los apellidos son más largos de lo que se puede guardar.', 100],
            'email' => ['El correo es más largo de lo que se puede guardar.', 150],
        ];
        foreach ($largos as $campo => [$porQue, $maximo]) {
            if (mb_strlen($datos[$campo]) > $maximo) {
                return ['campo' => $campo, 'porQue' => $porQue, 'enCampo' => "Máximo {$maximo} caracteres.",
                    'queHacer' => "Acórtalo a {$maximo} caracteres o menos y vuelve a guardar."];
            }
        }

        if (!filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) {
            return ['campo' => 'email', 'porQue' => 'El correo electrónico no es válido.', 'enCampo' => 'Revisa el correo.',
                'queHacer' => 'Escríbelo completo, por ejemplo nombre@colegio.edu.co, y vuelve a guardar.'];
        }

        // Al crear es obligatoria; al editar solo se valida si se escribió una nueva.
        if (!$esEdicion || $password !== '') {
            $errorContrasena = PoliticaContrasena::validar(
                $password,
                [$datos['email'], $datos['documento'], $datos['nombres'], $datos['apellidos']]
            );
            if ($errorContrasena !== null) {
                return ['campo' => 'password', 'porQue' => $errorContrasena, 'enCampo' => 'Revisa la contraseña.',
                    'queHacer' => 'Escribe una contraseña que cumpla esa condición y vuelve a guardar.'];
            }
        }

        if (!$this->rolPermitido($datos['rol_id'])) {
            return ['campo' => 'rol_id', 'porQue' => 'Tu usuario no puede asignar ese rol.', 'enCampo' => 'Elige otro rol.',
                'queHacer' => 'Elige otro rol de la lista y vuelve a guardar.'];
        }

        if (Usuario::existeDocumento($datos['documento'], $exceptId)) {
            return ['campo' => 'documento', 'porQue' => "Ya existe un usuario con el documento {$datos['documento']}.", 'enCampo' => 'Este documento ya existe.',
                'queHacer' => 'Revisa el número; si es la misma persona, edita el usuario que ya existe.',
                'accion' => Aviso::accion('Buscar en Usuarios', '/usuarios?' . http_build_query(['q' => $datos['documento']]))];
        }

        if (Usuario::existeEmail($datos['email'], $exceptId)) {
            return ['campo' => 'email', 'porQue' => "Ya existe un usuario con el correo {$datos['email']}.", 'enCampo' => 'Este correo ya existe.',
                'queHacer' => 'Usa otro correo; si es la misma persona, edita el usuario que ya existe.',
                'accion' => Aviso::accion('Buscar en Usuarios', '/usuarios?' . http_build_query(['q' => $datos['email']]))];
        }

        return null;
    }

    private function rolPermitido(int $rolId): bool
    {
        foreach (Usuario::rolesParaSelect(Auth::esSuperusuario()) as $rol) {
            if ((int) $rol['id'] === $rolId) {
                return true;
            }
        }

        return false;
    }

    /**
     * $datosAConservar: si la sesión ya expiró (token CSRF inválido) antes de esta
     * verificación, se pierde igual la oportunidad de flashOld() más abajo en el método —
     * por eso cada llamador ya construye sus $datos ANTES de este chequeo y los pasa aquí,
     * para que el usuario no pierda todo lo que había escrito solo porque se demoró
     * llenando el formulario y el token expiró mientras tanto. Nunca incluye la contraseña
     * (datosDesdeFormulario() no la trae; se lee aparte y nunca se flashea).
     */
    private function verificarCsrf(Request $request, string $volverA, array $datosAConservar = []): void
    {
        Csrf::verificarORedirigir($request, $volverA, $datosAConservar);
    }

    /**
     * @phpstan-assert array $usuario
     */
    private function verificarAcceso(?array $usuario): void
    {
        if (!$usuario) {
            http_response_code(404);
            View::render('errors/404');
            exit;
        }

        if (!Auth::esSuperusuario() && (int) $usuario['institucion_id'] !== Auth::institucionId()) {
            http_response_code(403);
            View::render('errors/403');
            exit;
        }

        // Una cuenta de superusuario solo la administra otro superusuario. Sin esto, un
        // rector que comparta institución con el superusuario (el sembrado vive en la
        // institución Demo) podría cambiarle la contraseña, desactivarlo, eliminarlo o
        // degradarlo a otro rol.
        if (!Auth::esSuperusuario() && ($usuario['rol_nombre'] ?? '') === 'superusuario') {
            http_response_code(403);
            View::render('errors/403');
            exit;
        }
    }

    /**
     * Cargos activos para el <select>, más el cargo actual del usuario si fue
     * desactivado después de asignárselo (para no perderlo del formulario al guardar).
     */
    private function cargosParaFormulario(array $usuario): array
    {
        $cargos = Cargo::activos();

        foreach ($cargos as $cargo) {
            if ((int) $cargo['id'] === (int) $usuario['cargo_id']) {
                return $cargos;
            }
        }

        $cargoActual = Cargo::find((int) $usuario['cargo_id']);
        if ($cargoActual) {
            $cargoActual['nombre'] .= ' (inactivo)';
            $cargos[] = $cargoActual;
        }

        return $cargos;
    }

    /**
     * Instituciones activas para el <select>, más la institución actual del usuario si fue
     * desactivada después de asignársela (para no perderla del formulario al guardar).
     */
    private function institucionesParaFormulario(array $usuario): array
    {
        $instituciones = Institucion::listadoParaSelect(true);

        foreach ($instituciones as $institucion) {
            if ((int) $institucion['id'] === (int) $usuario['institucion_id']) {
                return $instituciones;
            }
        }

        $institucionActual = Institucion::find((int) $usuario['institucion_id']);
        if ($institucionActual) {
            $institucionActual['nombre'] .= ' (inactiva)';
            $instituciones[] = $institucionActual;
        }

        return $instituciones;
    }
}
