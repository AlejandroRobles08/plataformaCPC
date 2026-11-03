<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps({
	users: {
		type: Object
	},
	// solo es verdadero para el super_admin
	verRoles: {
		type: Boolean,
		default: false
	},
	roles: {
		type: Array,
		default: () => []
	}
})
</script>

<template>
	<Head title="Users" />
	
	<AuthenticatedLayout>
		<template #header>
			Usuarios
		</template>
		
		<div class="p-4 bg-white rounded-lg shadow-xs">
			<div class="inline-flex overflow-hidden mb-4 w-full bg-white rounded-lg shadow-md">
				<div class="flex justify-center items-center w-12 bg-blue-500">
					<svg class="w-6 h-6 text-white fill-current" viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
						<path
							d="M20 3.33331C10.8 3.33331 3.33337 10.8 3.33337 20C3.33337 29.2 10.8 36.6666 20 36.6666C29.2 36.6666 36.6667 29.2 36.6667 20C36.6667 10.8 29.2 3.33331 20 3.33331ZM21.6667 28.3333H18.3334V25H21.6667V28.3333ZM21.6667 21.6666H18.3334V11.6666H21.6667V21.6666Z"></path>
					</svg>
				</div>
				
				<div class="px-4 py-2 -mx-3">
					<div class="mx-3">
						<span class="font-semibold text-blue-500">Roles de usuarios</span>
						<p class="text-sm text-gray-600">Se mostraran los roles y permisos que tiene cada usuario.</p>
					</div>
				</div>
			</div>
			
			<div class="overflow-hidden mb-8 w-full rounded-lg border shadow-xs">
				<div class="overflow-x-auto w-full">
					<table class="w-full whitespace-no-wrap">
						<thead>
							<tr class="text-xs font-semibold tracking-wide text-left text-gray-500 uppercase bg-gray-50 border-b">
								<th class="px-4 py-3">Nombre</th>
								<th class="px-4 py-3">Email</th>
								<th v-if="verRoles" class="px-4 py-3">Rol</th>
								<th v-if="verRoles" class="px-4 py-3">Permisos</th>
							</tr>
						</thead>
						<tbody class="bg-white divide-y">
							<tr v-for="user in users.data" :key="user.id" class="text-gray-700">
								<td class="px-4 py-3 text-sm">
									{{ user.name }}
								</td>
								<td class="px-4 py-3 text-sm">
									{{ user.email }}
								</td>
								<td v-if="verRoles" class="px-4 py-3 text-sm">
									<div class="flex flex-wrap gap-1">
										<span v-for="rol in user.roles" :key="rol"
											class="px-2 py-1 text-xs font-semibold text-blue-700 bg-blue-100 rounded-full">
											{{ rol }}
										</span>
										<span v-if="!user.roles.length" class="text-gray-400">Sin rol</span>
									</div>
								</td>
								<td v-if="verRoles" class="px-4 py-3 text-sm">
									<details v-if="user.permisos.length">
										<summary class="text-blue-600 cursor-pointer hover:underline">
											Ver {{ user.permisos.length }} permisos
										</summary>
										<div class="flex flex-wrap gap-1 mt-2">
											<span v-for="permiso in user.permisos" :key="permiso"
												class="px-2 py-1 text-xs text-gray-700 bg-gray-100 rounded">
												{{ permiso }}
											</span>
										</div>
									</details>
									<span v-else class="text-gray-400">Sin permisos</span>
								</td>
							</tr>
						</tbody>
					</table>
				</div>
				<div
					class="px-4 py-3 text-xs font-semibold tracking-wide text-gray-500 uppercase bg-gray-50 border-t sm:grid-cols-9">
					<pagination :links="users.links" />
				</div>
			</div>

			<!-- Catálogo de roles registrados (solo super_admin) -->
			<div v-if="verRoles" class="overflow-hidden mb-8 w-full rounded-lg border shadow-xs">
				<div class="px-4 py-3 text-xs font-semibold tracking-wide text-gray-500 uppercase bg-gray-50 border-b">
					Roles registrados y sus permisos
				</div>
				<div class="divide-y">
					<div v-for="rol in roles" :key="rol.id" class="px-4 py-3">
						<p class="mb-2 text-sm font-semibold text-gray-800">
							{{ rol.name }}
							<span class="ml-1 text-xs font-normal text-gray-500">({{ rol.permisos.length }} permisos)</span>
						</p>
						<div class="flex flex-wrap gap-1">
							<span v-for="permiso in rol.permisos" :key="permiso"
								class="px-2 py-1 text-xs text-gray-700 bg-gray-100 rounded">
								{{ permiso }}
							</span>
							<span v-if="!rol.permisos.length" class="text-xs text-gray-400">Sin permisos asignados</span>
						</div>
					</div>
				</div>
			</div>
		</div>
	</AuthenticatedLayout>
</template>
