import { useAuthStore } from '../stores/auth';



export function usePermission() {

    const auth = useAuthStore();



    const roles = () => (Array.isArray(auth.roles) ? auth.roles : []);

    const permissions = () => (Array.isArray(auth.permissions) ? auth.permissions : []);



    const can = (permission) => {

        if (roles().includes('admin')) return true;

        if (!permission) return true;

        return permissions().includes(permission);

    };



    const canAny = (list) => (Array.isArray(list) ? list : []).some((p) => can(p));



    const hasAnyRole = (list) => {

        if (!Array.isArray(list) || list.length === 0) return true;

        const userRoles = roles();

        return list.some((r) => userRoles.includes(r));

    };



    return { can, canAny, hasAnyRole };

}

