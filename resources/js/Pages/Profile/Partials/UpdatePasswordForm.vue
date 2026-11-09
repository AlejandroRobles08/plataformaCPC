<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { EyeIcon, EyeSlashIcon, LockClosedIcon } from '@heroicons/vue/24/outline';
import { useForm } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import Swal from 'sweetalert2';

const CAMPOS = [
    { id: 'current_password', etiqueta: 'Contraseña actual', autocomplete: 'current-password' },
    { id: 'password', etiqueta: 'Nueva contraseña', autocomplete: 'new-password' },
    { id: 'password_confirmation', etiqueta: 'Repetir nueva contraseña', autocomplete: 'new-password' },
];

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const visible = reactive({
    current_password: false,
    password: false,
    password_confirmation: false,
});

// Solo orienta al usuario: la regla que se aplica es la del servidor
const seguridad = computed(() => {
    const valor = form.password;
    const puntos = [
        valor.length >= 8,
        valor.length >= 12,
        /[a-z]/.test(valor) && /[A-Z]/.test(valor),
        /\d/.test(valor) && /[^A-Za-z0-9]/.test(valor),
    ].filter(Boolean).length;

    if (valor.length < 8) {
        return { etiqueta: 'Muy corta', ancho: 'w-1/4', barra: 'bg-red-500', texto: 'text-red-600' };
    }
    if (puntos <= 2) {
        return { etiqueta: 'Aceptable', ancho: 'w-1/2', barra: 'bg-yellow-500', texto: 'text-yellow-700' };
    }
    if (puntos === 3) {
        return { etiqueta: 'Buena', ancho: 'w-3/4', barra: 'bg-green-500', texto: 'text-green-600' };
    }
    return { etiqueta: 'Fuerte', ancho: 'w-full', barra: 'bg-green-600', texto: 'text-green-700' };
});

const enfocar = (id) => document.getElementById(id)?.focus();

const actualizar = () => {
    form.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            Object.keys(visible).forEach((campo) => {
                visible[campo] = false;
            });

            Swal.fire({
                icon: 'success',
                title: 'Contraseña actualizada',
                text: 'Tu contraseña se actualizó correctamente.',
                confirmButtonText: 'Aceptar',
                confirmButtonColor: '#15803d',
            });
        },
        onError: () => {
            if (form.errors.password) {
                form.reset('password', 'password_confirmation');
                enfocar('password');
            }

            if (form.errors.current_password) {
                form.reset('current_password');
                enfocar('current_password');
            }
        },
    });
};
</script>

<template>
    <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <header class="flex items-center justify-between gap-4 border-b border-gray-100 p-6">
            <div>
                <h3 class="text-base font-bold text-gray-900">
                    Restablecer contraseña
                </h3>
                <p class="mt-1 text-xs text-gray-500">
                    Asegúrate de que tu cuenta utilice una contraseña larga y aleatoria.
                </p>
            </div>
            <span class="shrink-0 rounded-lg bg-green-50 p-2 text-green-600" aria-hidden="true">
                <LockClosedIcon class="h-5 w-5" />
            </span>
        </header>

        <form class="space-y-4 p-6" @submit.prevent="actualizar">
            <div v-for="campo in CAMPOS" :key="campo.id">
                <InputLabel
                    :for="campo.id"
                    :value="campo.etiqueta"
                    class="mb-1.5 !text-xs font-semibold uppercase tracking-wider"
                />

                <div class="relative">
                    <TextInput
                        :id="campo.id"
                        v-model="form[campo.id]"
                        :type="visible[campo.id] ? 'text' : 'password'"
                        class="!mt-0 block w-full pr-10 text-sm focus:border-green-500 focus:ring-green-500"
                        :autocomplete="campo.autocomplete"
                    />
                    <button
                        type="button"
                        class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-400 hover:text-gray-600"
                        :aria-label="visible[campo.id] ? 'Ocultar contraseña' : 'Mostrar contraseña'"
                        @click="visible[campo.id] = !visible[campo.id]"
                    >
                        <EyeSlashIcon v-if="visible[campo.id]" class="h-4 w-4" />
                        <EyeIcon v-else class="h-4 w-4" />
                    </button>
                </div>

                <div v-if="campo.id === 'password' && form.password" class="mt-2">
                    <div class="h-1.5 w-full overflow-hidden rounded-full bg-gray-100">
                        <div class="h-1.5 rounded-full transition-all" :class="[seguridad.ancho, seguridad.barra]"></div>
                    </div>
                    <p class="mt-1 flex justify-between text-[11px] text-gray-400">
                        <span>
                            Nivel de seguridad:
                            <strong :class="seguridad.texto">{{ seguridad.etiqueta }}</strong>
                        </span>
                        <span>Mínimo 8 caracteres</span>
                    </p>
                </div>

                <InputError class="mt-2" :message="form.errors[campo.id]" />
            </div>

            <div class="flex justify-end pt-3">
                <PrimaryButton :disabled="form.processing" class="disabled:opacity-50">
                    Actualizar contraseña
                </PrimaryButton>
            </div>
        </form>
    </section>
</template>
