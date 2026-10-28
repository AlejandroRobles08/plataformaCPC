# Plataforma CPC — Diagramas de flujo del sistema

**Base tecnológica:** Laravel 13, PHP 8.3, Vue 3, Inertia.js, Spatie Laravel Permission y MySQL.

> **Criterio de documentación:** se reflejan las reglas y módulos que se han trabajado en el proyecto. Los elementos que se han descrito como pendientes o en proceso se marcan como tales y no se presentan como funcionalidades terminadas.

## 1. Flujo general: autenticación, privacidad, contraseña y acceso por rol

```mermaid
flowchart TD
    A([Inicio]) --> B[Usuario captura credenciales]
    B --> C{¿Credenciales válidas?}
    C -- No --> D[Mostrar error de autenticación]
    D --> B
    C -- Sí --> E{¿Cuenta existente y habilitada?}
    E -- No --> D
    E -- Sí --> F[Crear sesión autenticada]
    F --> G{¿Debe cambiar contraseña?}
    G -- Sí --> H[Mostrar modal de cambio de contraseña]
    H --> I{¿Contraseña nueva válida y confirmada?}
    I -- No --> H
    I -- Sí --> J[Guardar contraseña y actualizar indicador]
    G -- No --> K
    J --> K{¿Aviso de privacidad aceptado?}
    K -- No --> L[Mostrar aviso de privacidad]
    L --> M{¿Acepta el aviso?}
    M -- No --> N[Cerrar sesión y redirigir a login]
    N --> B
    M -- Sí --> O[Registrar aceptación según la lógica implementada]
    K -- Sí --> P[Resolver rol y permisos]
    O --> P
    P --> Q[Redirigir al dashboard autorizado]
    Q --> R{Rol del usuario}
    R -- super_admin --> S[Acceso administrativo total autorizado]
    R -- admin --> T[Acceso administrativo según permisos]
    R -- integrante --> U[Acceso limitado a su identidad, fórmula y consejo]
    R -- invitado --> V[Acceso únicamente a las funciones permitidas]
    S --> W[Menú y rutas protegidos por permisos]
    T --> W
    U --> W
    V --> W
    W --> X[Ejecutar módulo]
    X --> Y{¿Autorizado y cumple las validaciones?}
    Y -- No --> Z[Denegar acción o mostrar validación]
    Y -- Sí --> AA[Procesar operación y persistir cambios]
    AA --> AB([Fin de la operación])
```

## 2. Roles y alcance de acceso

```mermaid
flowchart TD
    A[Usuario autenticado] --> B{Rol asignado}
    B --> C[super_admin]
    B --> D[admin]
    B --> E[integrante]
    B --> F[invitado]

    C --> C1[Acceso administrativo global mediante la regla de super_admin]
    D --> D1[Acceso a operaciones administrativas autorizadas por permisos]
    E --> E1[Ver su información personal]
    E1 --> E2[Ver el nombre de la otra persona de su fórmula]
    E2 --> E3[No acceder a documentos ni historial de la otra persona]
    E3 --> E4[Consultar sesiones de su consejo y sus propios registros]
    F --> F1[Acceder solo a las vistas y acciones habilitadas para invitado]

    C1 --> G[Middleware, permisos, políticas y autorización del controlador]
    D1 --> G
    E4 --> G
    F1 --> G
    G --> H{¿La acción y el registro están autorizados?}
    H -- No --> I[Denegar acceso]
    H -- Sí --> J[Continuar con la operación]
```

**Reglas transversales de seguridad**
- Ocultar una opción del menú no sustituye la autorización del backend.
- Validar tanto el permiso para ejecutar la acción como la pertenencia del registro al usuario, fórmula o consejo correspondiente.
- Un integrante puede identificar a su compañero de fórmula, pero no consultar sus documentos ni su historial individual.
- Las rutas, controladores, políticas y consultas deben aplicar el mismo alcance de acceso.
- `super_admin` cuenta con una regla global de autorización; las operaciones de `admin` dependen de sus permisos.

## 3. Mapa de módulos

```mermaid
flowchart LR
    A[Dashboard] --> B[Consejos]
    A --> C[Integrantes]
    A --> D[Archivo digital y documentos]
    A --> E[Convocatorias]
    A --> F[Asistencia y participación]
    A --> G[Legalidad: periodo en el cargo]
    A --> H[Postulaciones]
    A --> I[Reportes]
    A --> J[Usuarios, roles y permisos]
    A --> K[Notificaciones]

    B --> C
    C --> D
    E --> F
    C --> F
    C --> G
    H --> L{Postulación aprobada}
    L -- Sí --> C
    D --> K
    E --> K
    F --> K
    G --> K
    H --> K
```

## 4. Consejos e integrantes

```mermaid
flowchart TD
    A[Entrar a Consejos] --> B[Seleccionar consejo]
    B --> C{¿El rol puede consultar este consejo?}
    C -- No --> D[Denegar acceso]
    C -- Sí --> E[Consultar integrantes permitidos]
    E --> F{¿Rol integrante?}
    F -- No --> G[Mostrar listado administrativo autorizado]
    F -- Sí --> H[Filtrar por identidad y fórmula del integrante]
    H --> I[Mostrar su ficha y el nombre de su compañero]
    G --> J[Seleccionar operación]
    I --> J
    J --> K{Operación}
    K -- Crear o editar --> L[Validar campos y reglas de negocio]
    K -- Baja --> M[Validar motivo y datos de baja]
    K -- Ver documentos --> N[Comprobar pertenencia del integrante]
    K -- Ver historial --> O[Comprobar pertenencia del integrante]
    L --> P{¿Datos válidos y permiso concedido?}
    P -- No --> Q[Mostrar errores o denegar]
    P -- Sí --> R[Guardar cambios]
    M --> R
    N --> S{¿Son documentos propios o tiene permiso administrativo?}
    S -- No --> Q
    S -- Sí --> T[Mostrar documentos autorizados]
    O --> U{¿Es historial propio o tiene permiso administrativo?}
    U -- No --> Q
    U -- Sí --> V[Mostrar historial autorizado]
```

### Reglas de negocio relacionadas
- El catálogo contempla 22 consejos.
- Una fórmula está integrada por exactamente 2 integrantes.
- Se ha trabajado con un mínimo de 10 fórmulas por consejo; el máximo de 20 debe tratarse como regla configurable/pendiente de corroboración antes de presentarlo como definitivo.
- Se contempla un máximo de 2 periodos por integrante dentro del mismo consejo.
- Las bajas distinguen motivos como inasistencia y fin de periodo, según el modelo definido.

## 5. Archivo digital y validación de documentos

```mermaid
flowchart TD
    A[Entrar a Archivo digital / Documentos] --> B{¿Acceso autorizado al integrante?}
    B -- No --> C[Denegar acceso]
    B -- Sí --> D[Consultar checklist documental]
    D --> E{¿Acción?}
    E -- Consultar estado --> F[Mostrar documentos y estatus]
    E -- Subir documento --> G[Recibir archivo PDF]
    G --> H{¿PDF y tipo documental permitido?}
    H -- No --> I[Rechazar archivo y mostrar error]
    H -- Sí --> J[Validar integrante, tipo y duplicidad]
    J --> K{¿Cumple validaciones?}
    K -- No --> I
    K -- Sí --> L[Guardar archivo y registro]
    L --> M[Documento pendiente de revisión]
    M --> N[Admin o super_admin revisa]
    N --> O{¿Documento válido?}
    O -- No --> P[Marcar rechazado o solicitar corrección según flujo disponible]
    O -- Sí --> Q[Marcar validado]
    Q --> R[Actualizar estado visual / semáforo]
    P --> S[Notificar al integrante según flujo disponible]
    Q --> S
```

**Tipos documentales trabajados:** INE, comprobante domiciliario, carta bajo protesta (art. 170), integración de fórmula, currículum vitae, carta de motivos (opcional) y cumplimiento de normatividad. Se ha manejado un máximo de 7 documentos para el registro inicial; la carta de motivos es opcional.

## 6. Postulaciones

```mermaid
flowchart TD
    A[Ingresar a Postulaciones] --> B{¿Rol y permiso autorizan la vista?}
    B -- No --> C[Denegar acceso]
    B -- Sí --> D[Consultar o registrar postulación]
    D --> E[Validar campos y documentos requeridos]
    E --> F{¿Información completa y válida?}
    F -- No --> G[Mostrar errores y permitir corrección]
    G --> D
    F -- Sí --> H[Guardar postulación con estatus correspondiente]
    H --> I[Admin autorizado revisa expediente]
    I --> J{¿Decisión?}
    J -- Pendiente --> K[Mantener pendiente]
    K --> I
    J -- Rechazar --> L[Actualizar estatus a rechazado]
    J -- Aprobar --> M[Actualizar estatus a aprobado]
    M --> N[Crear registro de integrante asociado]
    N --> O[Asignación de cuenta/rol de integrante]
    L --> P[Actualizar listado y notificaciones si están habilitadas]
    O --> P
```

**Nota de alcance:** la aprobación y creación del integrante forman parte del flujo de negocio definido. La creación automática de la cuenta de usuario asociada y la asignación de rol se han planteado como trabajo por completar; deben marcarse como pendientes hasta verificar el código actual.

## 7. Convocatorias, sesiones, asistencia y participación

```mermaid
flowchart TD
    A[Entrar a Convocatorias / Calendario] --> B{¿Rol?}
    B -- admin o super_admin --> C[Consultar y programar sesiones]
    B -- integrante --> D[Consultar sesiones de su consejo]
    C --> E[Seleccionar fecha]
    E --> F{¿Ya existe sesión en esa fecha?}
    F -- Sí --> G[Abrir formulario de asistencia de esa sesión]
    F -- No --> H[Abrir modal para programar sesión]
    H --> I[Validar fecha, tipo y datos requeridos]
    I --> J{¿Validación correcta?}
    J -- No --> K[Mostrar errores]
    J -- Sí --> L[Guardar sesión]
    L --> M[Mostrar sesión en calendario]
    G --> N[Registrar asistencia y participación]
    N --> O[Validar integrante, sesión, fecha y tipo]
    O --> P{¿Datos válidos?}
    P -- No --> K
    P -- Sí --> Q[Guardar asistencia]
    D --> R[Consultar sesión]
    R --> S[Enviar justificante PDF desde formulario de integrante]
    S --> T[Validar archivo y asociarlo a sesión/fecha]
    T --> U[Guardar justificante pendiente de revisión]
    U --> V[Admin o super_admin revisa justificante]
    V --> W[Actualizar estado y mostrar evidencia autorizada]
    Q --> X[Actualizar historial y semáforo]
    W --> X
```

**Validación clave:** una sesión no debe marcarse como realizada si su convocatoria no está previamente marcada como realizada. Los registros deben asociarse a la sesión correcta, evitando desfases de fecha o asociación con otra sesión. El calendario es consultable por integrantes; las acciones administrativas de programación y captura se restringen según rol.

## 8. Legalidad: periodo en el cargo

```mermaid
flowchart TD
    A[Entrar a Legalidad / Periodo en el cargo] --> B{¿Rol y registro autorizados?}
    B -- No --> C[Denegar acceso]
    B -- Sí --> D{¿Acción?}
    D -- Consulta administrativa --> E[Consultar periodos de integrantes autorizados]
    D -- Solicitud del integrante --> F[Consultar o solicitar su propio periodo]
    F --> G[Validar consejo, integrante y periodos previos]
    G --> H{¿Cumple reglas?}
    H -- No --> I[Rechazar solicitud o mostrar validación]
    H -- Sí --> J[Registrar solicitud pendiente]
    J --> K[Admin o super_admin revisa]
    K --> L{¿Decisión?}
    L -- Aprobar --> M[Registrar/actualizar periodo validado]
    L -- Rechazar --> N[Registrar rechazo y motivo si aplica]
    E --> O[Mostrar información según permisos]
    M --> P[Actualizar estado y notificar si está habilitado]
    N --> P
```

La regla trabajada establece un máximo de dos periodos por integrante en el mismo consejo. El flujo de solicitud y notificación de periodos se ha considerado una fase posterior y debe identificarse como pendiente si aún no está implementado por completo.

## 9. Reportes y dashboard

```mermaid
flowchart TD
    A[Entrar a Dashboard o Reportes] --> B{¿Permiso de consulta?}
    B -- No --> C[Denegar acceso]
    B -- Sí --> D{¿Rol?}
    D -- Admin o super_admin --> E[Mostrar indicadores y reportes administrativos]
    D -- Integrante --> F[Mostrar únicamente indicadores permitidos]
    E --> G[Aplicar filtros autorizados]
    G --> H[Contar registros y agrupar resultados]
    H --> I[Mostrar reportes de convocatorias, documentos, integrantes y bajas]
    I --> J{¿Exportación disponible para el reporte?}
    J -- Sí --> K[Generar exportación configurada]
    J -- No --> L[Consultar en pantalla]
    F --> M[Calcular datos limitados al alcance permitido]
    M --> N[Mostrar dashboard sin datos administrativos ajenos]
```

Los reportes trabajados contemplan conteos (sin porcentajes), una tabla dinámica de convocatorias, checklist documental y tabla de bajas. Se indicó que la tabla de bajas no requiere exportación. El dashboard de integrante debe recibir datos compatibles con sus componentes y no depender de propiedades administrativas ausentes.

## 10. Notificaciones

```mermaid
flowchart TD
    A[Ocurre una acción en un módulo] --> B{¿La acción genera notificación?}
    B -- No --> C[Continuar operación]
    B -- Sí --> D[Determinar destinatarios autorizados]
    D --> E{Tipo de destinatario}
    E -- Admin o super_admin --> F[Pendientes de postulaciones, documentos o validaciones]
    E -- Integrante --> G[Sesiones programadas o documentos validados]
    F --> H[Guardar notificación vinculada al usuario]
    G --> H
    H --> I[Mostrar campana y contador]
    I --> J[Usuario abre la notificación]
    J --> K[Validar acceso al destino]
    K --> L[Navegar al módulo y marcar como leída]
```

Las notificaciones deben filtrarse en backend por usuario, rol y relaciones pertinentes; no basta con ocultarlas en la interfaz. La notificación de resolución de solicitudes de periodo se dejó para una fase posterior.

## 11. Cierre de sesión y protección de datos

```mermaid
flowchart TD
    A[Usuario solicita cerrar sesión] --> B[Invalidar sesión autenticada]
    B --> C[Regenerar token CSRF]
    C --> D[Redirigir a login]
    D --> E[Usuario intenta regresar con atrás/adelante]
    E --> F{¿Existe sesión válida?}
    F -- No --> G[No mostrar vistas protegidas y solicitar login]
    F -- Sí --> H[Volver a validar autorización y alcance del registro]
```

## 12. Controles que atraviesan todos los módulos

1. **Autenticación:** comprobar credenciales y sesión.
2. **Autorización:** aplicar middleware, permisos Spatie, políticas y validaciones de controladores.
3. **Alcance del registro:** validar usuario, consejo, fórmula y propiedad del documento/historial.
4. **Validación de datos:** campos obligatorios, tipos de archivo, estados permitidos, relaciones y duplicidades.
5. **Persistencia:** guardar solo después de superar validaciones.
6. **Interfaz:** mostrar menús, botones, estados y errores coherentes con el permiso real.
7. **Sesión y navegación:** impedir el acceso a vistas privadas después de cerrar sesión.
8. **Notificaciones:** resolver destinatarios desde el backend y validar el destino antes de navegar.

## Pendientes que conviene confirmar antes de declarar el diagrama como “implementación final”

- Si el aviso de privacidad se persiste en base de datos o únicamente se controla mediante sesión/interfaz.
- Si la cuenta y el rol de integrante se crean automáticamente al aprobar una postulación.
- El máximo definitivo de fórmulas por consejo.
- El flujo completo de solicitud, aprobación y notificación del periodo en el cargo.
- Los estados exactos de rechazo/corrección de documentos y justificantes.
- Los permisos efectivos del rol `invitado` en el código actual.

