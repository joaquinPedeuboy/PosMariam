import { Categoria } from '@/types';

interface CategoriaConHijos extends Categoria {
    hijos: CategoriaConHijos[];
}

function construirArbol(categorias: Categoria[], padreId: number | null = null): CategoriaConHijos[] {
    return categorias
        .filter((c) => (c.categoria_padre_id ?? null) === padreId)
        .sort((a, b) => a.orden - b.orden)
        .map((c) => ({ ...c, hijos: construirArbol(categorias, c.id) }));
}

function Nodo({
    nodo,
    nivel,
    seleccionadas,
    onToggle,
}: {
    nodo: CategoriaConHijos;
    nivel: number;
    seleccionadas: number[];
    onToggle: (id: number) => void;
}) {
    return (
        <div>
            <label
                className="flex items-center gap-2 py-1 text-sm text-stone-700 cursor-pointer"
                style={{ paddingLeft: `${nivel * 18}px` }}
            >
                <input
                    type="checkbox"
                    checked={seleccionadas.includes(nodo.id)}
                    onChange={() => onToggle(nodo.id)}
                />
                {nodo.nombre}
            </label>
            {nodo.hijos.map((hijo) => (
                <Nodo
                    key={hijo.id}
                    nodo={hijo}
                    nivel={nivel + 1}
                    seleccionadas={seleccionadas}
                    onToggle={onToggle}
                />
            ))}
        </div>
    );
}

interface Props {
    categorias: Categoria[];
    seleccionadas: number[];
    onToggle: (id: number) => void;
}

/**
 * Muestra las categorías como árbol (padres con sus hijas indentadas
 * debajo), no como lista plana — necesario desde que las categorías
 * pueden tener varios niveles de profundidad. Se puede tildar cualquier
 * nivel: la categoría padre, una hija, o varias combinadas, porque un
 * producto puede pertenecer a más de una categoría a la vez.
 */
export default function CategoriaCheckboxTree({ categorias, seleccionadas, onToggle }: Props) {
    const arbol = construirArbol(categorias);

    if (arbol.length === 0) {
        return <p className="text-sm text-stone-400">Todavía no hay categorías creadas.</p>;
    }

    return (
        <div className="border border-stone-200 rounded-md p-3 max-h-64 overflow-y-auto">
            {arbol.map((nodo) => (
                <Nodo
                    key={nodo.id}
                    nodo={nodo}
                    nivel={0}
                    seleccionadas={seleccionadas}
                    onToggle={onToggle}
                />
            ))}
        </div>
    );
}
