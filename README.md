# Sistema de Gestión y Control de Asistencia Escolar — EET N.º 2 «Prof. Jesús Raúl Salazar»

Plataforma institucional web (Versión 1.0 - MVP) desarrollada para el **Nivel Secundario Técnico (1° a 6° año)** de la **Escuela de Educación Técnica N.º 2 «Prof. Jesús Raúl Salazar»** (San Salvador de Jujuy, Jujuy, Argentina), alineada con la estructura de datos y reportes oficiales del **SINIDE** (Sistema Integral de Información Digital Educativa).

---

## Stack Tecnológico

- **Backend:** PHP 8.2+ · Laravel 12 (MVC + FormRequests + Services + Eloquent ORM)
- **Base de Datos:** MySQL / MariaDB (XAMPP)
- **Frontend:** Blade + Tailwind CSS + Alpine.js
- **Exportaciones y Reportes:** `barryvdh/laravel-dompdf` (`.pdf` institucional) + procesador nativo OpenXML/CSV (`.xlsx` y `.csv` UTF-8 con BOM)

---

## Módulos Principales

1. **Control de Acceso y Delegación (RBAC):**
   - **Director:** Acceso global al Dashboard, gestión de usuarios/credenciales, matriz de preceptores (`course_user`), organización escolar, calendario de feriados, bitácora (`audit_logs`) y visor privado de certificados médicos.
   - **Operador (Preceptor / Jefe de Taller / Ed. Física):** Restringido por el middleware `CheckCourseAssignment` exclusivamente a sus cursos, turnos y ámbitos asignados.
   - **Docente:** Panel de solo consulta.
2. **Organización Escolar y Personas (con Baja Lógica Estricta):**
   - ABM de Cursos/Divisiones, Especialidades, Turnos, Ámbitos (`scopes`) y Actividades/Materias.
   - Padrón SINIDE de Estudiantes y Plantel Docente (estado `ACTIVO` / `INACTIVO`, prohibido el borrado físico `DELETE` con historial).
   - **Importador Masivo Excel/CSV en 9 Pasos** con descarga de plantilla oficial, vista previa de validación y guardado **Upsert por DNI**.
3. **Toma de Asistencia y Justificaciones Documentadas:**
   - **Toma Diaria Contextualizada (`Fecha -> Turno -> Ámbito -> Curso -> Actividad`)** sincronizada con el reloj del servidor para Estudiantes y Docentes.
   - **Grilla Mensual Interactiva estilo SINIDE (`01` al `31`)**.
   - **Modal Obligatorio de Inasistencia Justificada (`IJ`):** bloquea justificaciones vacías en frontend y backend, exigiendo certificado (`.pdf`, `.png`, `.jpg`, `.jpeg`) y/o motivo escrito (`>= 10` caracteres).
4. **Consultas, Planilla Mensual y Exportaciones:**
   - Consulta Histórica combinada, Ranking de Inasistencias con niveles de alerta, Historial Individual por Estudiante y Planilla Mensual (`P, A, T, IJ` o nomenclatura oficial `SINIDE: P, C, Q, M, J`) exportables a **Excel (`.xlsx`)**, **CSV (`.csv`)** y **PDF (`.pdf`)**.

---

## Instalación en Entorno Local (XAMPP / Windows)

```powershell
# 1. Clonar el repositorio
git clone <URL_DEL_REPOSITORIO> asistencia-escolar
cd asistencia-escolar

# 2. Instalar dependencias PHP
composer install

# 3. Configurar variables de entorno
copy .env.example .env
php artisan key:generate

# 4. Crear la base de datos `asistencia_escolar` en MySQL (phpMyAdmin) y ejecutar migraciones + seeders
php artisan migrate --seed

# 5. Habilitar enlace público de almacenamiento
php artisan storage:link

# 6. Iniciar el servidor local
php artisan serve
```

---

## Credenciales de Prueba (Seeders de Desarrollo)

Contraseña para todas las cuentas: `password`

| Rol | Usuario | Email | Alcance Inicial |
| :--- | :--- | :--- | :--- |
| **Director** | `admin` | `admin@eet2jujuy.edu.ar` | Acceso global completo |
| **Preceptor (Operador)** | `preceptor_clases` | `preceptor@eet2jujuy.edu.ar` | Clases (`3° 1°`, `4° 1°`, `5° 1°`) |
| **Prof. Ed. Física (Operador)** | `profe_edfisica` | `edfisica@eet2jujuy.edu.ar` | Educación Física (`1° 1°`, `2° 1°`, `3° 1°`) |
| **Prof. Taller (Operador)** | `profe_taller` | `taller@eet2jujuy.edu.ar` | Taller (`5° 1°`, `5° 2°`, `6° 1°`) |
| **Docente** | `docente_consulta` | `docente@eet2jujuy.edu.ar` | Panel de solo consulta |

---

## Ejecución de Pruebas Automatizadas

```powershell
php artisan test
```
