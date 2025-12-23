import React from 'react';
import { LockOutlined, CrownOutlined } from '@ant-design/icons';

const LockedFeature = ({
    title,
    description,
    children,
    upgradeUrl = 'https://genwave.ai/plans',
    compact = false
}) => {
    if (compact) {
        // Compact mode: inline badge next to title
        return (
            <div className="locked-feature locked-feature--compact">
                <div className="locked-feature__inline">
                    <div className="locked-feature__content-wrapper">
                        {children}
                    </div>
                    <div className="locked-feature__inline-badge">
                        <LockOutlined />
                        <span>Pro</span>
                    </div>
                </div>
            </div>
        );
    }

    // Full mode: overlay with details
    return (
        <div className="locked-feature">
            <div className="locked-feature__content">
                {children}
            </div>
            <div className="locked-feature__overlay">
                <div className="locked-feature__badge">
                    <LockOutlined className="locked-feature__icon" />
                    <span className="locked-feature__label">Pro Feature</span>
                </div>
                <h4 className="locked-feature__title">{title}</h4>
                <p className="locked-feature__description">{description}</p>
                <a
                    href={upgradeUrl}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="locked-feature__upgrade-btn"
                >
                    <CrownOutlined /> Upgrade to Pro
                </a>
            </div>
        </div>
    );
};

export default LockedFeature;
