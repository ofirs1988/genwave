import React, { useState, useEffect } from 'react';
import {
    ThunderboltOutlined,
    FileTextOutlined,
    LineChartOutlined,
    ClockCircleOutlined,
    PieChartOutlined,
    BarChartOutlined,
    CrownOutlined,
    CheckCircleOutlined,
    RocketOutlined,
    PictureOutlined,
    AudioOutlined,
    LoadingOutlined
} from '@ant-design/icons';
import LockedFeature from '../../components/LockedFeature';

const Dashboard = () => {
    const [stats, setStats] = useState({
        tokenBalance: 0,
        totalRequests: 0,
        loading: true
    });

    useEffect(() => {
        // Wait for settings to be available
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
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'genwave_get_dashboard_stats',
                    security: window.genwaveFreeSettings.nonce
                })
            });

            const data = await response.json();
            if (data.success) {
                setStats({
                    tokenBalance: data.data.token_balance || 0,
                    totalRequests: data.data.total_requests || 0,
                    loading: false
                });
            } else {
                setStats(prev => ({ ...prev, loading: false }));
            }
        } catch (error) {
            console.error('Failed to fetch stats:', error);
            setStats(prev => ({ ...prev, loading: false }));
        }
    };

    const formatNumber = (num) => {
        if (num >= 1000000) {
            return (num / 1000000).toFixed(1) + 'M';
        }
        if (num >= 1000) {
            return (num / 1000).toFixed(1) + 'K';
        }
        return num.toLocaleString();
    };

    return (
        <div className="gw-page">
            {/* Header */}
            <div className="gw-page__header">
                <h1 className="gw-page__title">Dashboard</h1>
                <p className="gw-page__subtitle">Overview of your AI content generation</p>
            </div>

            {/* Upgrade Banner */}
            <div className="gw-upgrade-banner">
                <div className="gw-upgrade-banner__content">
                    <h3>Unlock Full Power with Pro</h3>
                    <p>Get unlimited access to all AI features</p>
                    <div className="gw-upgrade-banner__features">
                        <span className="gw-upgrade-banner__feature">
                            <CheckCircleOutlined /> Bulk Generation
                        </span>
                        <span className="gw-upgrade-banner__feature">
                            <PictureOutlined /> AI Images
                        </span>
                        <span className="gw-upgrade-banner__feature">
                            <AudioOutlined /> AI Audio
                        </span>
                        <span className="gw-upgrade-banner__feature">
                            <BarChartOutlined /> Analytics
                        </span>
                    </div>
                </div>
                <a
                    href="https://genwave.ai/plans"
                    target="_blank"
                    rel="noopener noreferrer"
                    className="gw-upgrade-banner__btn"
                >
                    <CrownOutlined /> Upgrade Now
                </a>
            </div>

            {/* Stats Cards */}
            <div className="gw-cards-grid">
                {/* Token Balance - Active */}
                <div className="gw-stat-card gw-stat-card--gradient">
                    <div className="gw-stat-card__icon">
                        <ThunderboltOutlined />
                    </div>
                    <div className="gw-stat-card__label">Token Balance</div>
                    <div className="gw-stat-card__value">
                        {stats.loading ? <LoadingOutlined className="gw-spinner-icon" /> : formatNumber(stats.tokenBalance)}
                    </div>
                </div>

                {/* Total Requests - Active */}
                <div className="gw-stat-card">
                    <div className="gw-stat-card__icon">
                        <FileTextOutlined />
                    </div>
                    <div className="gw-stat-card__label">Total Requests</div>
                    <div className="gw-stat-card__value">
                        {stats.loading ? <LoadingOutlined className="gw-spinner-icon" /> : formatNumber(stats.totalRequests)}
                    </div>
                </div>

                {/* Tokens Used - Locked */}
                <LockedFeature
                    title="Tokens Used"
                    description="Track detailed token usage and consumption"
                    compact
                >
                    <div className="gw-stat-card">
                        <div className="gw-stat-card__icon">
                            <LineChartOutlined />
                        </div>
                        <div className="gw-stat-card__label">Tokens Used</div>
                        <div className="gw-stat-card__value">0</div>
                    </div>
                </LockedFeature>

                {/* AI Products - Locked */}
                <LockedFeature
                    title="AI Products"
                    description="View all products enhanced with AI"
                    compact
                >
                    <div className="gw-stat-card">
                        <div className="gw-stat-card__icon">
                            <RocketOutlined />
                        </div>
                        <div className="gw-stat-card__label">AI Products</div>
                        <div className="gw-stat-card__value">0</div>
                    </div>
                </LockedFeature>
            </div>

            {/* Locked Sections */}
            <div className="gw-cards-grid">
                {/* Usage Chart - Locked */}
                <LockedFeature
                    title="Usage Analytics"
                    description="View detailed charts of your AI usage over time"
                >
                    <div className="gw-section" style={{ minHeight: '300px' }}>
                        <h3 className="gw-section__title">
                            <LineChartOutlined /> Usage Over Time
                        </h3>
                        <div style={{ height: '200px', background: 'var(--gw-gray-100)', borderRadius: '8px' }}></div>
                    </div>
                </LockedFeature>

                {/* Activity Log - Locked */}
                <LockedFeature
                    title="Recent Activity"
                    description="Track all your AI generation activities"
                >
                    <div className="gw-section" style={{ minHeight: '300px' }}>
                        <h3 className="gw-section__title">
                            <ClockCircleOutlined /> Recent Activity
                        </h3>
                        <div style={{ height: '200px', background: 'var(--gw-gray-100)', borderRadius: '8px' }}></div>
                    </div>
                </LockedFeature>
            </div>

            {/* More Locked Analytics */}
            <div className="gw-cards-grid">
                <LockedFeature
                    title="Content Types"
                    description="See distribution of generated content types"
                >
                    <div className="gw-section" style={{ minHeight: '250px' }}>
                        <h3 className="gw-section__title">
                            <PieChartOutlined /> Content Types
                        </h3>
                        <div style={{ height: '160px', background: 'var(--gw-gray-100)', borderRadius: '8px' }}></div>
                    </div>
                </LockedFeature>

                <LockedFeature
                    title="Models Used"
                    description="Analytics on AI models usage"
                >
                    <div className="gw-section" style={{ minHeight: '250px' }}>
                        <h3 className="gw-section__title">
                            <BarChartOutlined /> Models Used
                        </h3>
                        <div style={{ height: '160px', background: 'var(--gw-gray-100)', borderRadius: '8px' }}></div>
                    </div>
                </LockedFeature>
            </div>
        </div>
    );
};

export default Dashboard;
