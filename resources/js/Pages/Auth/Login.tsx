import { useForm } from '@inertiajs/react';
import { SubmitEventHandler  } from 'react';

export default function Login() {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit: SubmitEventHandler<HTMLFormElement> = (e) => {
        e.preventDefault();
        post('/login');
    };

    return (
        <form onSubmit={submit} className="max-w-sm mx-auto mt-20 space-y-4">
            <h1 className="text-xl font-semibold">Ingresar al panel</h1>

            <div>
                <input
                    type="email"
                    value={data.email}
                    onChange={(e) => setData('email', e.target.value)}
                    className="w-full border rounded px-3 py-2"
                    placeholder="Email"
                />
                {errors.email && <p className="text-red-600 text-sm">{errors.email}</p>}
            </div>

            <div>
                <input
                    type="password"
                    value={data.password}
                    onChange={(e) => setData('password', e.target.value)}
                    className="w-full border rounded px-3 py-2"
                    placeholder="Contraseña"
                />
            </div>

            <button
                type="submit"
                disabled={processing}
                className="w-full bg-black text-white rounded py-2"
            >
                Ingresar
            </button>
        </form>
    );
}