<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { UserIcon } from '@heroicons/vue/24/outline';
import { useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
    integrante: {
        type: Object,
        default: null,
    },
});

// El nombre y el correo de un integrante los administra el personal desde su expediente
const soloLectura = computed(() => props.integrante !== null);

const user = usePage().props.auth.user;

const form = useForm({
    name: user.name,
    email: user.email,
});

const claseEtiqueta = 'mb-1.5 !text-xs font-semibold uppercase tracking-wider';
const claseCampo = 'block w-full !mt-0 text-sm focus:border-green-500 focus:ring-green-500';
const claseCampoBloqueado = '!mt-0 block w-full cursor-not-allowed border-gray-200 bg-gray-100 text-sm text-gray-600 focus:border-gray-200 focus:ring-0';

const guardar = () => {
    form.patch(route('profile.update'), {
        preserveScroll: true,
        onSuccess: () => {
            Swal.fire({
                icon: 'success',
                title: 'Información actualizada',
                text: 'Los datos de tu cuenta se actualizaron correctamente.',
                confirmButtonText: 'Aceptar',
                confirmButtonColor: '#15803d',
            });
        },
    });
};
</script>

<template>
    <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <header class="flex items-center justify-between gap-4 border-b border-gray-100 p-6">
            <div>
                <h3 class="text-base font-bold text-gray-900">
                    Información de la cuenta
                </h3>
                <p class="mt-1 text-xs text-gray-500">
                    {{ soloLectura
                        ? 'Consulta los datos registrados en tu expediente.'
                        : 'Actualiza tu nombre y correo electrónico.' }}
                </p>
            </div>
            <span class="shrink-0 rounded-lg bg-green-50 p-2 text-green-600" aria-hidden="true">
                <UserIcon class="h-5 w-5" />
            </span>
        </header>

        <form class="space-y-4 p-6" @submit.prevent="guardar">
            <div>
                <InputLabel for="name" value="Nombre completo" :class="claseEtiqueta" />
                <TextInput
                    id="name"
                    v-model="form.name"
                    type="text"
                    :class="soloLectura ? claseCampoBloqueado : claseCampo"
                    :readonly="soloLectura"
                    required
                    autocomplete="name"
                />
                <InputError class="mt-2" :message="form.errors.name" />
            </div>

            <div>
                <InputLabel for="email" value="Correo electrónico" :class="claseEtiqueta" />
                <TextInput
                    id="email"
                    v-model="form.email"
                    type="email"
                    :class="soloLectura ? claseCampoBloqueado : claseCampo"
                    :readonly="soloLectura"
                    required
                    autocomplete="username"
                />
                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div v-if="integrante" class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <InputLabel for="consejo" value="Consejo" :class="claseEtiqueta" />
                    <TextInput
                        id="consejo"
                        :model-value="integrante.consejo"
                        type="text"
                        :class="claseCampoBloqueado"
                        readonly
                    />
                </div>
                <div>
                    <InputLabel for="cargo" value="Cargo" :class="claseEtiqueta" />
                    <TextInput
                        id="cargo"
                        :model-value="integrante.cargo"
                        type="text"
                        :class="claseCampoBloqueado"
                        readonly
                    />
                </div>
            </div>

            <p v-if="soloLectura" class="pt-1 text-xs text-gray-500">
                Si necesitas corregir algún dato, solicítalo al personal de administración.
            </p>

            <div v-else class="flex justify-end pt-3">
                <PrimaryButton :disabled="form.processing" class="disabled:opacity-50">
                    Guardar
                </PrimaryButton>
            </div>
        </form>
    </section>
</template>
