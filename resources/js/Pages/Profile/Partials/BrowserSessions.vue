<script setup>
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { ComputerDesktopIcon, DevicePhoneMobileIcon } from '@heroicons/vue/24/outline';
import { useForm } from '@inertiajs/vue3';
import { computed, nextTick, ref } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
    sesiones: {
        type: Array,
        default: () => [],
    },
});

const hayOtrasSesiones = computed(() => props.sesiones.some((sesion) => !sesion.actual));

const confirmando = ref(false);

const form = useForm({
    password: '',
});

const enfocarContrasena = () => document.getElementById('sesiones_password')?.focus();

const abrirConfirmacion = () => {
    confirmando.value = true;
    nextTick(enfocarContrasena);
};

const cerrarConfirmacion = () => {
    confirmando.value = false;
    form.clearErrors();
    form.reset();
};

const cerrarOtrasSesiones = () => {
    form.delete(route('profile.sessions.destroy'), {
        preserveScroll: true,
        onSuccess: () => {
            cerrarConfirmacion();

            Swal.fire({
                icon: 'success',
                title: 'Sesiones cerradas',
                text: 'Se cerró tu sesión en los demás dispositivos.',
                confirmButtonText: 'Aceptar',
                confirmButtonColor: '#15803d',
            });
        },
        onError: () => {
            form.reset();
            enfocarContrasena();
        },
    });
};
</script>

<template>
    <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        <h3 class="text-base font-bold text-gray-900">
            Sesión actual y dispositivos
        </h3>
        <p class="mt-1 text-xs text-gray-500">
            Estos son los dispositivos con una sesión abierta en tu cuenta.
        </p>

        <ul class="mt-4 space-y-3">
            <li
                v-for="(sesion, indice) in sesiones"
                :key="indice"
                class="flex items-center justify-between gap-3 rounded-lg border border-gray-200 bg-gray-50 p-3.5"
            >
                <div class="flex min-w-0 items-center gap-3.5">
                    <div
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg"
                        :class="sesion.actual ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-600'"
                        aria-hidden="true"
                    >
                        <DevicePhoneMobileIcon v-if="sesion.movil" class="h-5 w-5" />
                        <ComputerDesktopIcon v-else class="h-5 w-5" />
                    </div>

                    <div class="min-w-0">
                        <p class="text-xs font-semibold text-gray-800">
                            {{ sesion.dispositivo }}
                        </p>
                        <p v-if="sesion.actual" class="text-[11px] font-semibold text-green-600">
                            Este dispositivo
                        </p>
                        <p v-else class="text-[11px] text-gray-500">
                            Última actividad {{ sesion.ultima_actividad }}
                        </p>
                    </div>
                </div>

                <span
                    v-if="sesion.actual"
                    class="h-2.5 w-2.5 shrink-0 rounded-full bg-green-500 ring-4 ring-green-100"
                    aria-hidden="true"
                ></span>
            </li>
        </ul>

        <div v-if="hayOtrasSesiones" class="mt-4 flex items-center justify-between gap-4 pt-2">
            <span class="text-xs text-gray-500">¿No reconoces alguno?</span>
            <button
                type="button"
                class="shrink-0 text-xs font-semibold text-red-600 hover:text-red-700 hover:underline"
                @click="abrirConfirmacion"
            >
                Cerrar otras sesiones
            </button>
        </div>
        <p v-else class="mt-4 text-xs text-gray-500">
            No tienes sesiones abiertas en otros dispositivos.
        </p>

        <Modal :show="confirmando" max-width="md" @close="cerrarConfirmacion">
            <form class="p-6" @submit.prevent="cerrarOtrasSesiones">
                <h3 class="text-lg font-medium text-gray-900">
                    Cerrar otras sesiones
                </h3>
                <p class="mt-1 text-sm text-gray-600">
                    Escribe tu contraseña para cerrar tu sesión en los demás dispositivos.
                    En este dispositivo seguirás con tu sesión abierta.
                </p>

                <div class="mt-5">
                    <InputLabel for="sesiones_password" value="Contraseña" />
                    <TextInput
                        id="sesiones_password"
                        v-model="form.password"
                        type="password"
                        class="block w-full text-sm focus:border-green-500 focus:ring-green-500"
                        autocomplete="current-password"
                    />
                    <InputError class="mt-2" :message="form.errors.password" />
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton @click="cerrarConfirmacion">
                        Cancelar
                    </SecondaryButton>
                    <DangerButton :disabled="form.processing" class="disabled:opacity-50">
                        Cerrar sesiones
                    </DangerButton>
                </div>
            </form>
        </Modal>
    </section>
</template>
