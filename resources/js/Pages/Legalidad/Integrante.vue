<script>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Link } from '@inertiajs/vue3';

export default {
    components: {
        AuthenticatedLayout,
        Link
    },
    props:{
        consejo: {
            type: Object,
            required: true,
        },
        integrantes: {
            type: Object,
            default: () => [],
        },
        registros: {
            type: Object,
            default: () => [],
        },
    },

    computed: {
        usuario() {
            return this.$page.props.auth?.user ?? {};
        },
        integrante() {
            return (this.registros [0]?. integrante ?? 
            this.integrantes[0] ?? 
            this.usuario.integrante ?? {}
            )
        }
    }
}

</script>