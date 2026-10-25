import React from 'react';
import ReactDOM from 'react-dom/client';
import App from './app/App';
import './app/app.css';

// One app for every Genwave screen (Core\AdminApp prints the root and the route).
const root = document.getElementById('genwave-app');
if (root) {
    ReactDOM.createRoot(root).render(<App initialRoute={root.dataset.route || 'overview'} />);
}
