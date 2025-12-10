/**
 * Simple popup test - load this instead of the complex one
 */

(function() {
    'use strict';

    function SimpleTestPopup(props) {
        const { onClose } = props;
        
        return React.createElement('div', {
            className: 'ai-generator-popup-overlay',
            onClick: onClose,
            style: {
                position: 'fixed',
                top: 0,
                left: 0,
                right: 0,
                bottom: 0,
                backgroundColor: 'rgba(0, 0, 0, 0.6)',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                zIndex: 100000
            }
        },
            React.createElement('div', {
                className: 'ai-generator-popup',
                onClick: (e) => e.stopPropagation(),
                style: {
                    backgroundColor: 'white',
                    borderRadius: '12px',
                    padding: '20px',
                    maxWidth: '500px',
                    width: '90%'
                }
            },
                React.createElement('h2', null, '🤖 AI Generator Test'),
                React.createElement('p', null, 'This is a simple test popup to verify React is working.'),
                React.createElement('p', null, 'Post ID: ' + props.postId),
                React.createElement('p', null, 'Post Title: ' + props.postTitle),
                React.createElement('p', null, 'Post Type: ' + props.postType),
                React.createElement('button', {
                    onClick: onClose,
                    style: {
                        padding: '10px 20px',
                        backgroundColor: '#1890ff',
                        color: 'white',
                        border: 'none',
                        borderRadius: '6px',
                        cursor: 'pointer',
                        marginTop: '20px'
                    }
                }, 'Close')
            )
        );
    }

    // Export to global scope
    window.AIContentGeneratorPopup = SimpleTestPopup;

})();