import React from 'react';
import ReactDOM from 'react-dom/client';
import Dashboard from './pages/Dashboard';
import Generate from './pages/Generate';
import './styles/main.css';

// Mount Dashboard
const dashboardRoot = document.getElementById('genwave-dashboard-app');
if (dashboardRoot) {
    const root = ReactDOM.createRoot(dashboardRoot);
    root.render(<Dashboard />);
}

// Mount Generate
const generateRoot = document.getElementById('genwave-generate-app');
if (generateRoot) {
    const root = ReactDOM.createRoot(generateRoot);
    root.render(<Generate />);
}
