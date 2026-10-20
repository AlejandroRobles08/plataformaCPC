<script setup>
import { ref, watch } from 'vue';
import DraggableModal from '@/Components/DraggableModal.vue';

const props = defineProps({
    show: Boolean,
    form: Object,
    editingId: Number,
});

const emit = defineEmits(['close', 'submit']);

const showDiscapacidadTipo = ref(false);

watch(
    () => props.form.discapacidad,
    (newVal) => {

        showDiscapacidadTipo.value = !!newVal;

        if (!newVal) {
            props.form.discapacidad_tipo = '';
        }

    }
);
</script>

<template>
    <DraggableModal v-if="show"
        :title="editingId ? 'Editar Integrante' : 'Nuevo Integrante'"
        max-width="max-w-2xl" @close="$emit('close')">
        <form @submit.prevent="$emit('submit')" class="space-y-4">
            <!-- Nombre -->
            <div>
                <label class="block text-sm font-medium">
                    Nombre
                </label>
                <input v-model="form.nombre" type="text"
                    class="w-full border rounded px-3 py-2"/>
                <p v-if="form.errors.nombre" class="text-red-500 text-sm">
                    {{ form.errors.nombre }}
                </p>
            </div>

            <!-- Apellido paterno -->
            <div>
                <label class="block text-sm font-medium">
                    Apellido paterno
                </label>
                <input v-model="form.apellido_paterno" type="text"
                class="w-full border rounded px-3 py-2"/>
                <p v-if="form.errors.apellido_paterno" class="text-red-500 text-sm">
                    {{ form.errors.apellido_paterno }}
                </p>
            </div>

            <!-- Apellido materno -->
            <div>
                <label class="block text-sm font-medium">
                    Apellido materno
                </label>
                <input v-model="form.apellido_materno" type="text"
                class="w-full border rounded px-3 py-2"/>
                <p v-if="form.errors.apellido_materno" class="text-red-500 text-sm">
                    {{ form.errors.apellido_materno }}
                </p>
            </div>

            <!-- Fórmula: captura manual desactivada, ahora se asigna automáticamente al dar de alta
            <div>
                <label class="block text-sm font-medium">
                    Fórmula
                </label>
                <input type="number" min="1" v-model="form.formula"
                    class="w-full border rounded px-3 py-2"/>
            </div>
            -->

            <!-- Cargo -->
            <div>
                <label class="block text-sm font-medium">
                    Cargo
                </label>
                <input v-model="form.puesto" type="text" class="w-full border rounded px-3 py-2"/>
                <p v-if="form.errors.puesto" class="text-red-500 text-sm">
                    {{ form.errors.puesto }}
                </p>
            </div>

            <!-- Correo -->
            <div>
                <label class="block text-sm font-medium">
                    Correo
                </label>
                <input v-model="form.correo" type="email" class="w-full border rounded px-3 py-2"/>
                <p v-if="form.errors.correo" class="text-red-500 text-sm">
                    {{ form.errors.correo }}
                </p>
            </div>

            <!-- Dirección -->
            <div>
                <label class="block text-sm font-medium">
                    Dirección
                </label>
                <textarea v-model="form.direccion" rows="2" maxlength="500"
                    class="w-full border rounded px-3 py-2"
                    placeholder="Calle, número, colonia, código postal"></textarea>
                <p v-if="form.errors.direccion" class="text-red-500 text-sm">
                    {{ form.errors.direccion }}
                </p>
            </div>

            <!-- Género -->
            <div>
                <label class="block text-sm font-medium">
                    Género
                </label>

                <select
                    v-model="form.genero"
                    class="w-full border rounded px-3 py-2">
                    <option value="">Seleccione</option>
                    <option value="Mujer">Femenino</option>
                    <option value="Hombre">Masculino</option>
                    <option value="Otro">
                        Otro
                    </option>
                    <option value="Prefiero no responder">
                        Prefiero no responder
                    </option>
                </select>

                <p
                    v-if="form.errors.genero"
                    class="text-red-500 text-sm"
                >
                    {{ form.errors.genero }}
                </p>
            </div>

            <!-- Discapacidad -->
            <div class="flex items-center space-x-2">
                <input type="checkbox" v-model="form.discapacidad" class="w-4 h-4"/>
                <label class="text-sm font-medium">
                    ¿Es usted discapacitado?
                </label>
            </div>

            <!-- Tipo de discapacidad -->
            <div v-if="showDiscapacidadTipo">
                <label class="block text-sm font-medium">
                    Por favor, especifique el tipo de discapacidad
                </label>
                <input v-model="form.discapacidad_tipo" type="text"
                    class="w-full border rounded px-3 py-2"/>
                <p v-if="form.errors.discapacidad_tipo" class="text-red-500 text-sm">
                    {{ form.errors.discapacidad_tipo }}
                </p>
            </div>
            <!-- Error de fórmula (p. ej. consejo con las 15 fórmulas completas) -->
            <p v-if="form.errors.formula" class="text-red-500 text-sm">
                {{ form.errors.formula }}
            </p>

            <!-- Botones -->
            <div class="flex justify-end space-x-2 pt-4">
                <button type="button" @click="$emit('close')"
                    class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-800">
                    Cancelar
                </button>

                <button type="submit" class="px-4 py-2 bg-green-600 text-white 
                rounded hover:bg-green-800">
                    Guardar
                </button>
            </div>
        </form>
    </DraggableModal>
</template>
