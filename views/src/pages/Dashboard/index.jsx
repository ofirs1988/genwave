import React, { useState, useEffect } from 'react';
import {
    ThunderboltOutlined,
    FileTextOutlined,
    LoadingOutlined,
    MessageOutlined,
    RocketOutlined,
    BugOutlined,
    ToolOutlined,
    ShopOutlined,
    ArrowRightOutlined,
} from '@ant-design/icons';

const Dashboard = () => {
    const [stats, setStats] = useState({
        creditBalance: 0,
        totalRequests: 0,
        loading: true,
    });

    useEffect(() => {
        const checkAndFetch = () => {
            if (window.genwaveFreeSettings?.ajaxurl) {
                fetchStats();
            } else {
                setTimeout(checkAndFetch, 100);
            }
        };
        checkAndFetch();
    }, []);

    const fetchStats = async () => {
        try {
            const response = await fetch(window.genwaveFreeSettings.ajaxurl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'genwave_get_dashboard_stats',
                    security: window.genwaveFreeSettings.nonce,
                }),
            });
            const data = await response.json();
            if (data.success) {
                setStats({
                    creditBalance: data.data.credit_balance || data.data.token_balance || 0,
                    totalRequests: data.data.total_requests || 0,
                    loading: false,
                });
            } else {
                setStats((prev) => ({ ...prev, loading: false }));
            }
        } catch (error) {
            console.error('Failed to fetch stats:', error);
            setStats((prev) => ({ ...prev, loading: false }));
        }
    };

    const formatNumber = (num) => {
        if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M';
        if (num >= 1000) return (num / 1000).toFixed(1) + 'K';
        return Number(num).toLocaleString(undefined, { maximumFractionDigits: 2 });
    };

    const agentUrl = '/wp-admin/admin.php?page=genwave-agent';

    return (
        <div className="gw-page gw-dash">
            <header className="gw-dash__head">
                <h1 className="gw-dash__title">Dashboard</h1>
                <p className="gw-dash__subtitle">An overview of your GenWave activity.</p>
            </header>

            {/* Real stats */}
            <div className="gw-dash__stats">
                <div className="gw-dash-stat gw-dash-stat--accent">
                    <span className="gw-dash-stat__ic"><ThunderboltOutlined /></span>
                    <div className="gw-dash-stat__body">
                        <span className="gw-dash-stat__label">Credit balance</span>
                        <span className="gw-dash-stat__value">
                            {stats.loading ? <LoadingOutlined /> : formatNumber(stats.creditBalance)}
                        </span>
                    </div>
                </div>
                <div className="gw-dash-stat">
                    <span className="gw-dash-stat__ic"><FileTextOutlined /></span>
                    <div className="gw-dash-stat__body">
                        <span className="gw-dash-stat__label">Total requests</span>
                        <span className="gw-dash-stat__value">
                            {stats.loading ? <LoadingOutlined /> : formatNumber(stats.totalRequests)}
                        </span>
                    </div>
                </div>
            </div>

            {/* Agent hero */}
            <section className="gw-dash-agent">
                <span className="gw-dash-agent__glow" aria-hidden="true" />
                <div className="gw-dash-agent__inner">
                    <span className="gw-dash-agent__badge"><MessageOutlined /> AI Agent</span>
                    <h2 className="gw-dash-agent__title">Manage your site through conversation</h2>
                    <p className="gw-dash-agent__lead">
                        Your personal AI assistant for WordPress — build, fix, create and optimize, all by chatting.
                    </p>

                    <div className="gw-dash-agent__feats">
                        <span className="gw-dash-agent__feat"><RocketOutlined /> Build plugins</span>
                        <span className="gw-dash-agent__feat"><BugOutlined /> Auto-fix errors</span>
                        <span className="gw-dash-agent__feat"><ToolOutlined /> Elementor widgets</span>
                        <span className="gw-dash-agent__feat"><ShopOutlined /> WooCommerce</span>
                    </div>

                    <div className="gw-dash-agent__actions">
                        <a className="gw-dash-agent__cta" href={agentUrl}>
                            Open the Agent <ArrowRightOutlined />
                        </a>
                        <a
                            className="gw-dash-agent__link"
                            href="https://genwave.ai/ai-agent"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            Learn more
                        </a>
                    </div>
                </div>
            </section>
        </div>
    );
};

export default Dashboard;
