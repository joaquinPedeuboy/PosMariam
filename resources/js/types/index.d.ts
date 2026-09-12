export interface User {
    id: number;
    name: string;
    email: string;
    admin: boolean;
}

export interface PageProps {
    auth: {
        user: User;
    };
    [key: string]: unknown;
}

export interface Categoria {
    id: number;
    nombre: string;
    slug: string;
    categoria_padre_id: number | null;
    orden: number;
    mostrar_en_menu_principal: boolean;
}

export interface Imagen {
    id: number;
    url: string;
    principal: boolean;
}

export interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    links: { url: string | null; label: string; active: boolean }[];
}