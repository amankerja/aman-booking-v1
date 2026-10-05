import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';

const appName = import.meta.env.VITE_APP_NAME || 'AMAN BOOKING';

createInertiaApp({
    id: 'app',
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) => {
        const pageName = name.startsWith('Public/')
            ? name.replace(/^Public\//, '')
            : name;
        return resolvePageComponent(
            `./Pages/Public/${pageName}.tsx`,
            import.meta.glob('./Pages/Public/**/*.tsx')
        );
    },
    setup({ el, App, props }) {
        if (!el) {
            throw new Error('[public.tsx] Inertia root element #app tidak ditemukan di DOM.');
        }
        const root = createRoot(el);
        root.render(<App {...props} />);
    },
    progress: {
        color: '#2563eb',
    },
});
