<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import BrowserSessions from './Partials/BrowserSessions.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { ShieldCheckIcon } from '@heroicons/vue/24/outline';
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({
    // Solo llega cuando el usuario es integrante de un consejo
    integrante: {
        type: Object,
        default: null,
    },
    sesiones: {
        type: Array,
        default: () => [],
    },
});

const ETIQUETAS_ROL = {
    super_admin: 'Super Admin',
    admin: 'Admin',
    integrante: 'Integrante',
    invitado: 'Invitado',
};

const page = usePage();
const user = computed(() => page.props.auth.user);

const iniciales = computed(() =>
    user.value.name
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((parte) => parte.charAt(0).toUpperCase())
        .join(''),
);

const rolAsignado = computed(() =>
    (page.props.auth.roles ?? [])
        .map((rol) => ETIQUETAS_ROL[rol] ?? rol)
        .join(', '),
);
</script>

<template>
    <Head title="Perfil de usuario" />

    <AuthenticatedLayout>
        <template #header>
            Perfil de usuario
        </template>

        <div class="min-w-0 space-y-6 pb-8">
            <section class="flex flex-col justify-between gap-6 rounded-xl border border-gray-200 bg-white p-6 shadow-sm md:flex-row md:items-center">
                <div class="flex min-w-0 items-center gap-5">
                    <div
                        class="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-gray-800 to-gray-900 text-2xl font-bold text-white shadow-md"
                        aria-hidden="true"
                    >
                        {{ iniciales }}
                    </div>

                    <div class="min-w-0">
                        <h3 class="break-words text-xl font-bold text-gray-900">
                            {{ user.name }}
                        </h3>
                        <p class="mt-0.5 break-all text-sm text-gray-500">
                            {{ user.email }}
                        </p>
                        <p v-if="integrante?.consejo" class="mt-2 text-xs font-medium text-gray-700">
                            {{ integrante.consejo }}
                        </p>
                    </div>
                </div>

                <div v-if="rolAsignado" class="shrink-0 rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 md:text-right">
                    <span class="block text-[11px] font-semibold uppercase tracking-wider text-gray-400">
                        Rol asignado
                    </span>
                    <span class="inline-flex items-center gap-1.5 text-sm font-bold text-gray-800">
                        <ShieldCheckIcon class="h-4 w-4 text-green-600" aria-hidden="true" />
                        {{ rolAsignado }}
                    </span>
                </div>
            </section>

            <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-2">
                <UpdateProfileInformationForm :integrante="integrante" />
                <div class="min-w-0 space-y-6">
                    <UpdatePasswordForm />
                    <BrowserSessions :sesiones="sesiones" />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
