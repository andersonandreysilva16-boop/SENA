# SeguPYME — capa de datos con Laravel (Eloquent + patrón Repository)

Implementación en Laravel de la base de datos de SeguPYME, siguiendo el **Manual de sentencias Eloquent** y el
proyecto base `Veterina` del instructor: modelos con atributos (`#[Table]`, `#[Fillable]`), repositorios con su
contrato, `RepositoryServiceProvider` y pruebas Pest contra MySQL real.

> **Modelo de datos que implementa:** la base oficial **v15** (`base_de_datos_v15`: 11 tablas, sin ciclos, con permisos por rol;
> la planilla llega a las personas por las novedades y la carga masiva es un procedimiento). Todavía no está aprobada por el instructor. Si el modelo
> cambia, lo único que cambia aquí son los modelos, los repositorios afectados y los scripts de `database/sql`
> (ver "Si cambia el modelo").

## Qué incluye y qué no

| Incluye | No incluye (igual que el proyecto base) |
|---|---|
| 12 modelos (11 tablas + la vista `v_planilla_total`) | Vistas (Blade) |
| 14 enums para las columnas `ENUM` | Generación del archivo PILA en el servidor |
| 11 repositorios con contrato + `BaseRepository` | Lectura del Excel de la carga masiva (la hace la página o la app) |
| 2 servicios: `PlanillaService` y `CargaMasivaService` | |
| **API REST** (`routes/api.php`) con login por token, novedades, empleados, empresas, planillas y carga masiva | |
| Traducción de errores de MySQL a HTTP | |
| 103 pruebas Pest (89 de la capa de datos + 14 de la API), conectadas con la cuenta reducida de la aplicación | |

## Requisitos

- **PHP 8.4.1 o superior.** `composer.json` dice `^8.3`, pero el `composer.lock` del proyecto base instala Symfony 8,
  que exige 8.4.1+. (El `AGENTS.md` del proyecto base instala PHP 8.5.)
- MySQL 8.0 y MySQL Workbench.
- Composer. **Atajo:** este proyecto tiene el mismo `composer.json` y `composer.lock` que `Veterina`, así que puedes copiar
  la carpeta `vendor` de `Veterina` en lugar de correr `composer install`.

## Puesta en marcha

1. **Crear la base de datos.** En Workbench, ejecuta en orden los scripts de `database/sql` (`01_crear_base_de_datos.sql` …
   `05_datos_de_prueba.sql` y `08_seguridad.sql`; ver `database/sql/LEEME.md`). Crea la base `segupyme_v15` y las cuentas
   `segupyme_app`, `segupyme_lectura` y `segupyme_migraciones` (**cambia sus contraseñas** fuera de pruebas).
2. `composer install` (o copiar `vendor`).
3. `copy .env.example .env`, poner en `DB_USERNAME`/`DB_PASSWORD` la cuenta **`segupyme_app`** (no `root`) y `php artisan key:generate`.
4. Para las pruebas: `copy .env.testing.example .env.testing` y poner en `DB_ADMIN_USERNAME`/`DB_ADMIN_PASSWORD` una cuenta
   administradora de MySQL (solo sirve para reconstruir la base de pruebas).

## Pruebas

```
php artisan test                 # todas las pruebas
php artisan test --group=mysql   # igual que en el proyecto base
php artisan test tests/Feature/Servicios
```

- Las pruebas **borran y vuelven a crear** la base `segupyme_v15_test` con los mismos scripts de `database/sql`
  (usando la cuenta administradora de `.env.testing`). Por seguridad, si el nombre no termina en `_test`, se detienen sin tocar nada.
- Las pruebas **se conectan como `segupyme_app`**, la misma cuenta reducida de la aplicación: así se prueba el camino real
  (escribir solo por procedimiento) y que un `INSERT` directo en `Novedad` o `Bitacora` falla con el error 1142 (→ 403).
- Cada archivo de pruebas parte de una base nueva (`baseNueva(__FILE__)` en su `beforeEach`), así que no dependen
  unas de otras. Dentro de `PlanillaServiceTest` las pruebas van en orden y se continúan (generar → aprobar → pagar → corregir).
- La base real (`segupyme_v15`) nunca se toca.

## Estructura

```
app/
  Enums/                    14 enums (EstadoAprobacion, EstadoPlanilla, TipoEntidad, RolUsuario...)
  Models/                   Rol, Empresa, Cargo, Usuario, Bitacora, Afiliacion, Concepto, Novedad,
                            ParametroLegal, Planilla, DetallePlanilla, PlanillaResumen (vista)
  Repositories/
    Contracts/              BaseRepositoryInterface + una interfaz por modelo
    Eloquent/               BaseRepository + una implementación por modelo
  Services/                 PlanillaService, CargaMasivaService
  Support/                  ErroresMysql, ResultadoCarga
  Exceptions/               ReglaDeNegocioException
  Providers/                AppServiceProvider (N+1 y morph map), RepositoryServiceProvider (11 enlaces)
database/sql/               Scripts de la base de datos (fuente de verdad del esquema)
docs/                       SQL_vs_Laravel_Eloquent_SeguPYME.xlsx (equivalencias SQL ↔ Eloquent, 127 filas)
tests/                      Database/, Models/, Repositories/, Servicios/, Support/BaseDePruebas.php
```

## Dónde está cada sección del manual

| Manual | En SeguPYME |
|---|---|
| 1. Configuración del modelo | `app/Models/*` (`#[Table]`, `#[Fillable]`, `casts()`); PK de texto en `Empresa` |
| 2–4. Lectura, `where`, orden y paginación | `app/Repositories/Eloquent/*` (`buscar()` con `when()` y `paginate()`) |
| 5. Agregados | `DetallePlanillaRepository::totalesPorPersona()` (SQL nativo) y la vista `v_planilla_total` |
| 6. Relaciones | `app/Models/*`; `with()` siempre antes de leer; `preventLazyLoading` en `AppServiceProvider` |
| 7–8. Escritura | `NovedadRepository::registrar()` (procedimiento con actor), `UsuarioRepository::create()` y `BaseRepository::create/update` |
| 9. Scopes, accessors y mutators | `Concepto::deHoras`, `Afiliacion::vigentesEn`, `Usuario::activos`, `DetallePlanilla::totalAportes` |
| 10. Transacciones y SQL crudo | Los procedimientos de MySQL llevan su propia transacción; llamadas `CALL` en `PlanillaRepository`, `NovedadRepository` y `AfiliacionRepository` |
| 11. Patrón Repository | `app/Repositories`, `RepositoryServiceProvider`, `app/Services` |
| 12. SQL nativo | `ConceptoRepository::findByCodigoSql()`, `ParametroLegalRepository::vigenteEnSql()`, `AfiliacionRepository::estaCompleta()` |
| Errores comunes (tabla de MySQL) | `app/Support/ErroresMysql.php` |

## Cómo se usa

Los controladores y servicios piden la **interfaz**; Laravel entrega la implementación Eloquent:

```php
class PlanillaController
{
    public function __construct(private PlanillaService $planillas) {}

    public function generar(Request $request, string $nit)
    {
        // El primer parametro es QUIEN ACTUA: la base revisa su rol (solo el Contador que atiende la empresa o el
        // Super Usuario generan). Si se niega (sin permiso, planilla repetida, afiliaciones incompletas...), lanza
        // ReglaDeNegocioException y Laravel responde 422 con el mensaje de la base.
        return $this->planillas->generar($request->user()->id_usuario, $nit, $request->periodo, $request->fecha_limite);
    }
}
```

Probar sin escribir un controlador (`php artisan tinker`):

```php
app(PlanillaService::class)->generar($idContador, '900111222', '2026-10-01', '2026-11-10')->total_aportes;   // "2252105.04"
app(UsuarioRepositoryInterface::class)->conAfiliacionesIncompletas('900111222', '2026-10-31');   // Pedro Diaz
app(CargaMasivaService::class)->novedades($idActor, '900111222', $filas);                       // ResultadoCarga
```

## API REST (la usan la página web y la app Flutter)

Todas las rutas empiezan por `/api`. Menos `POST /api/login`, todas piden `Authorization: Bearer <token>`.

| Ruta | Qué hace | Quién |
|---|---|---|
| `POST /login` | Correo y contraseña → token (8 h) y perfil. 5 fallos bloquean la cuenta 15 min | todos |
| `GET /yo` | Perfil de la sesión | todos |
| `GET /empresas` | Empresas que puede ver (la suya; el Contador, también sus clientes) | todos |
| `GET /conceptos` | Catálogo con banderas: `lleva_horas`, `requiere_fecha_fin`, `solo_dominical_festivo` | todos |
| `GET /novedades` | Lista (filtros `empresa`, `estado`, `periodo`, `id_empleado`) | el Empleado solo ve las suyas |
| `POST /novedades` | Registra novedad u horas extra (`concepto`, `fecha_inicio`, `fecha_fin`, `horas`, `empleado_id`) | Empleado (propias), Contador, Super Usuario |
| `PATCH /novedades/{id}` | `{"decision":"APROBADA"\|"RECHAZADA"}` | Administrador |
| `GET /empleados`, `GET /empleados/afiliaciones-incompletas` | Personas de una empresa | no el Empleado |
| `GET/POST /planillas`, `GET /planillas/{id}`, `POST /planillas/{id}/aprobar\|refrescar\|generada\|pago\|correccion`, `DELETE /planillas/{id}` | Ciclo de la planilla | según el rol (lo decide la base) |
| `POST /carga/novedades`, `POST /carga/empleados` | `{"empresa": "...", "filas": [...]}` → `{ok, errores, rechazadas[]}` | Contador, Administrador, Super Usuario |

**Cómo se reparte la seguridad**
- *Escribir*: lo decide **la base**. Cada procedimiento recibe quién actúa (`$request->user()->id_usuario`) y revisa su rol. Si se niega, el servicio lanza `ReglaDeNegocioException` y la API responde 422/403 con el mensaje de la base.
- *Leer*: la cuenta `segupyme_app` puede leer todo, así que la API filtra con `App\Support\Acceso` (usa `fn_empresa_usuario` y `fn_atiende` de la base).
- *Sesión*: token HS256 firmado con `APP_KEY` (`TokenService`), sin tabla de sesiones (el modelo de datos no la tiene). En cada petición se vuelve a leer la cuenta: si se bloquea o elimina, se corta el acceso.
- *Fechas*: las horas extra y recargos no admiten fechas futuras (trigger `trg_novedad_bi` en la base, y la API lo avisa antes). Los recargos dominicales/festivos solo admiten domingos y festivos (`App\Support\Festivos`, igual que la página y la app).
- Las contraseñas de `database/sql/05_datos_de_prueba.sql` son de relleno: para iniciar sesión hay que ponerle a alguien un hash real (por ejemplo en `php artisan tinker`: `Usuario::where('cedula','1012345678')->first()->update(['contrasena_hash' => password_hash('Clave*2026', PASSWORD_ARGON2ID)])`).

Probar: `php artisan serve --host=0.0.0.0 --port=8000` y, por ejemplo,
```
curl -X POST http://localhost:8000/api/login -H "Content-Type: application/json" -d '{"correo":"ana@textilesandina-prueba.co","contrasena":"Clave*2026"}'
```
Pruebas nuevas: `tests/Feature/Api/SinBaseTest.php` (no necesita MySQL) y `tests/Feature/Api/ApiTest.php` (contra MySQL).

## Decisiones y diferencias con el proyecto base

1. **El id de los repositorios acepta `int|string`**: `Empresa` usa el NIT (texto) como llave primaria.
2. **No hay migraciones.** El esquema, las reglas (triggers, CHECK) y los procedimientos viven en `database/sql`.
   Por eso `SESSION_DRIVER`, `CACHE_STORE` y `QUEUE_CONNECTION` usan archivos o memoria: así la base solo tiene las
   tablas del modelo y no aparecen `users`, `sessions`, `jobs` ni `migrations`.
3. **Las reglas de negocio no se repiten en PHP.** Las hace cumplir MySQL (triggers y procedimientos). Laravel las llama,
   y si la base se niega, `ErroresMysql` convierte el error en el código HTTP correcto (el error 1644 es el de los
   triggers y procedimientos).
4. **`periodo` y `dias` de `Novedad` son columnas generadas** por MySQL: no están en `#[Fillable]` y después de crear una
   novedad se llama `refresh()` para leerlas (lo hace `NovedadRepository::registrar()`).
5. **`DetallePlanilla` solo tiene las dos llaves en `#[Fillable]`**: los valores los calcula `sp_calcular_planilla`, y la aplicación no escribe esa tabla directo.
6. **La planilla no guarda la empresa ni el total.** Se leen del modelo `PlanillaResumen` (vista `v_planilla_total`).
7. **La carga masiva no es una tabla.** `NovedadRepository::cargarMasivo()` y `UsuarioRepository::cargarMasivo()` llaman a
   `sp_cargar_novedades` y `sp_cargar_empleados`, que reparten el archivo en las tablas existentes.
8. **La aplicación escribe directo solo `Usuario`, `Empresa` y `Cargos`** (cuenta `segupyme_app`). Novedades, afiliaciones, valores
   legales, planillas y bitácora se escriben llamando procedimientos que reciben **quién actúa** (`$idActor`) y revisan su rol:
   `NovedadRepository::registrar/gestionar/adjuntarSoporte/verSoporte`, `AfiliacionRepository::registrar/gestionar`,
   `ParametroLegalRepository::registrar`, `PlanillaRepository::generar/refrescar/descartar/aprobar/marcarGenerada/registrarPago/generarCorreccion`.
   `BitacoraRepository` es de solo lectura (la bitácora la escriben los procedimientos).
9. **No hay DELETE para la aplicación:** se usa baja lógica (`estado = INACTIVA`). Un intento falla con el error 1142 → 403.

## Si cambia el modelo

- Cambia una tabla o columna: ajustar `database/sql`, el modelo (`#[Table]`, `#[Fillable]`, `casts()`, relaciones) y,
  si hace falta, su repositorio. Las pruebas de `tests/Feature/Models` avisan lo que se rompe.
- Cambia el nombre de la base: `DB_DATABASE` en `.env` y `.env.testing` (debe terminar en `_test`).
