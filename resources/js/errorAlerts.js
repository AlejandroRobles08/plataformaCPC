import { router } from '@inertiajs/vue3';
import Swal from 'sweetalert2';

// Alertas globales de error. Se registran una sola vez desde app.js para cubrir
// todas las páginas, sin importar el layout.

const mostrarError = (titulo, mensaje) => {
    Swal.fire({
        icon: 'warning',
        title: titulo,
        text: mensaje,
        confirmButtonText: 'Entendido',
        confirmButtonColor: '#15803d',
    });
};

// Acción rechazada por el servidor (permisos, sesión expirada...): ver FriendlyErrorResponse.php.
// Se usa "success" y no "navigate" para no repetir la alerta al usar "atrás" en el navegador.
router.on('success', (event) => {
    const error = event.detail.page.props.flash?.errorGlobal;

    if (error) {
        mostrarError(error.titulo, error.mensaje);
    }
});

// Respuestas que no generó Laravel (p. ej. nginx) y que Inertia mostraría como un HTML en crudo
const RESPUESTAS_EXTERNAS = {
    413: ['Archivo demasiado grande', 'Los archivos que intentas subir superan el tamaño permitido. Reduce su tamaño o súbelos de uno en uno.'],
    502: ['Servicio no disponible', 'La plataforma no está respondiendo en este momento. Inténtalo de nuevo en unos minutos.'],
    503: ['Plataforma en mantenimiento', 'Estamos realizando mejoras en la plataforma. Vuelve a intentarlo en unos minutos.'],
    504: ['La solicitud tardó demasiado', 'El servidor tardó demasiado en responder. Inténtalo de nuevo en unos minutos.'],
};

router.on('invalid', (event) => {
    const status = event.detail.response.status;
    let error = RESPUESTAS_EXTERNAS[status];

    // En desarrollo se deja la pantalla de depuración de Laravel
    if (!error && status >= 500 && import.meta.env.PROD) {
        error = ['Algo salió mal', 'Ocurrió un error inesperado. Inténtalo de nuevo en unos minutos.'];
    }

    if (error) {
        event.preventDefault();
        mostrarError(...error);
    }
});

// Sin conexión o el servidor no respondió
router.on('exception', (event) => {
    const codigo = event.detail.exception?.code;

    // Otros errores (de JavaScript) siguen su flujo normal
    if (codigo !== 'ERR_NETWORK' && codigo !== 'ECONNABORTED') return;

    event.preventDefault();
    mostrarError(
        'Sin conexión',
        'No pudimos comunicarnos con la plataforma. Revisa tu conexión a internet e inténtalo de nuevo.',
    );
});
