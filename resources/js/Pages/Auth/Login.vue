<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import PrivacyModal from '@/Components/PrivacyModal.vue';
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const form = useForm({
	email: '',
	password: '',
	remember: false
});

// Aviso de privacidad: el backend lo pide (error "privacy") la primera vez que el usuario
// ingresa con credenciales válidas. Se muestra sobre el login y, al aceptarlo,
// se reenvía el mismo formulario indicando la aceptación.
const showPrivacy = ref(false);
const privacyRechazado = ref(false);

const enviar = (privacyAccepted = false) => {
	form
		.transform(data => (privacyAccepted ? { ...data, privacy_accepted: true } : data))
		.post(route('login'), {
			// El dashboard reemplaza al login en el historial: si no, "atrás" lo restauraría
			// desde history.state, sin pasar por el servidor, con la sesión ya iniciada.
			replace: true,
			onError: errors => {
				if (errors.privacy) {
					// Credenciales correctas: se conservan para reenviarlas al aceptar
					showPrivacy.value = true;
					return;
				}
				showPrivacy.value = false;
				form.reset('password');
			},
			onFinish: () => {
				if (!showPrivacy.value) form.reset('password');
			},
		});
};

const submit = () => {
	privacyRechazado.value = false;
	enviar();
};

// El modal sigue abierto (con "Procesando...") hasta que el login termina
const aceptarPrivacidad = () => enviar(true);

const rechazarPrivacidad = () => {
	showPrivacy.value = false;
	privacyRechazado.value = true;
	form.reset('password');
	form.clearErrors();
};
</script>

<template>
	<GuestLayout>
		<Head title="Log in" />		
        <div class="flex flex-col overflow-y-auto md:flex-row login-container">
			<div class="h-32 md:h-auto md:w-1/2">
				<img aria-hidden="true" class="object-cover w-full h-full" src="/images/gobpue.jpg" alt="Office" />
			</div>
			<div class="flex items-center justify-center p-6 sm:p-12 md:w-1/2">
				<div class="w-full">
					<h1 class="mb-4 text-xl font-semibold text-gray-700">Control de Acceso Plataforma CPC</h1>
					
					<div v-if="status" class="mb-4 text-sm font-medium text-green-600">
						{{ status }}
					</div>
					
					<div v-if="privacyRechazado" class="mb-4 text-sm font-medium text-red-600">
						Para ingresar a la plataforma es necesario aceptar el aviso de privacidad.
					</div>

					<form @submit.prevent="submit">
						<div class="mt-4">
							<InputLabel for="email" value="Correo" />
							<TextInput id="email" type="email" class="block w-full mt-1" v-model="form.email" required autofocus autocomplete="username" />
							<InputError class="mt-2" :message="form.errors.email" />
						</div>
						
						<div class="mt-4">
							<InputLabel for="password" value="Contraseña" />
							<TextInput id="password" type="password" class="block w-full mt-1" v-model="form.password" required autocomplete="current-password" />
							<InputError class="mt-2" :message="form.errors.password" />
						</div>
						
						<div class="flex items-center justify-end mt-4">
							<!--<Link v-if="canResetPassword" :href="route('password.request')" class="text-sm text-gray-600 underline  hover:text-gray-900">
								Olvidé mi contraseña
							</Link> -->
							<!--<PrimaryButton class="ml-4 bg-white hover:bg-white">
							</PrimaryButton>-->
							<PrimaryButton class="ml-5" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
								Ingresar
							</PrimaryButton>
						</div>
					</form>
				</div>
			</div>
		</div>
		<PrivacyModal v-if="showPrivacy" :processing="form.processing"
			@aceptar="aceptarPrivacidad" @rechazar="rechazarPrivacidad" />
	</GuestLayout>
</template>

<style scoped>
.login-container {
  /* Puedes usar cualquier color que quieras */
  background-color: #f0f2f5; /* Un gris claro, por ejemplo */
}
</style>