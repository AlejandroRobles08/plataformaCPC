<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { NoSymbolIcon, CalendarDaysIcon, BuildingLibraryIcon } from '@heroicons/vue/24/outline';

// motivo es null cuando la baja fue por error de registro: se muestra un aviso genérico
const props = defineProps({
	motivo: { type: String, default: null },
	fecha_baja: { type: String, default: null },
	consejo: { type: String, default: null },
});

const MOTIVOS = {
	inasistencia: {
		titulo: 'Inasistencias',
		texto: 'Tu cuenta se desactivó porque acumulaste faltas sin justificar a las sesiones del consejo.',
	},
	sancion: {
		titulo: 'Sanción',
		texto: 'Tu cuenta se desactivó como resultado de una sanción determinada por la administración.',
	},
	renuncia: {
		titulo: 'Renuncia',
		texto: 'Tu cuenta se desactivó porque se registró tu renuncia al cargo.',
	},
	fin_periodo: {
		titulo: 'Fin de periodo',
		texto: 'Tu cuenta se desactivó porque concluyó el periodo de tu cargo. Gracias por tu participación.',
	},
};

const detalle = computed(() => MOTIVOS[props.motivo] ?? null);

const fechaLegible = computed(() =>
	props.fecha_baja
		? new Date(`${props.fecha_baja}T00:00:00`).toLocaleDateString('es-MX', {
				day: 'numeric',
				month: 'long',
				year: 'numeric',
			})
		: null
);
</script>

<template>
	<GuestLayout>
		<Head title="Cuenta desactivada" />
		<div class="flex flex-col overflow-y-auto md:flex-row aviso-container">
			<div class="h-32 md:h-auto md:w-1/2">
				<img aria-hidden="true" class="object-cover w-full h-full" src="/images/gobpue.jpg" alt="" />
			</div>
			<div class="flex items-center justify-center p-6 sm:p-12 md:w-1/2">
				<div class="w-full" role="alert">
					<div class="flex items-center justify-center w-12 h-12 mb-4 bg-red-100 rounded-full">
						<NoSymbolIcon class="w-7 h-7 text-red-600" aria-hidden="true" />
					</div>

					<h1 class="mb-2 text-xl font-semibold text-gray-700">Tu cuenta está desactivada</h1>

					<template v-if="detalle">
						<div class="p-4 mb-4 bg-white border border-red-200 rounded-lg">
							<p class="text-xs font-semibold tracking-wide text-red-600 uppercase">Motivo de la baja</p>
							<p class="mt-1 text-base font-semibold text-gray-800">{{ detalle.titulo }}</p>
							<p class="mt-1 text-sm text-gray-600">{{ detalle.texto }}</p>

							<dl class="pt-3 mt-3 space-y-2 text-sm border-t border-gray-100">
								<div v-if="fechaLegible" class="flex items-center gap-2 text-gray-600">
									<CalendarDaysIcon class="w-4 h-4 text-gray-400 shrink-0" aria-hidden="true" />
									<dt class="sr-only">Fecha de baja</dt>
									<dd>Fecha de baja: <span class="font-medium text-gray-800">{{ fechaLegible }}</span></dd>
								</div>
								<div v-if="consejo" class="flex items-center gap-2 text-gray-600">
									<BuildingLibraryIcon class="w-4 h-4 text-gray-400 shrink-0" aria-hidden="true" />
									<dt class="sr-only">Consejo</dt>
									<dd> Consejo anterior: <span class="font-medium text-gray-800">{{ consejo }}</span></dd>
								</div>
							</dl>
						</div>

						<p class="mb-6 text-sm text-gray-600">
							Si consideras que se trata de un error o necesitas una aclaración, comunícate con la administración de los consejos.
						</p>
					</template>

					<p v-else class="mb-6 text-sm text-gray-600">
						Tu cuenta no está activa. Para más información, comunícate con la administración de los consejos.
					</p>

					<Link :href="route('login')"
						class="inline-flex items-center px-4 py-2 text-xs font-semibold tracking-widest text-white uppercase transition duration-150 ease-in-out bg-gray-800 border border-transparent rounded-md hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
						Volver al inicio de sesión
					</Link>
				</div>
			</div>
		</div>
	</GuestLayout>
</template>

<style scoped>
.aviso-container {
	background-color: #f0f2f5;
}
</style>
