/**
 * BTR Payment Context Block - Editor JavaScript
 * Blocco custom per l'editor WooCommerce checkout
 * 
 * @version 1.0.240
 */

(function() {
    'use strict';

    const { registerBlockType } = wp.blocks;
    const { createElement, Fragment } = wp.element;
    const { InspectorControls, BlockControls, AlignmentToolbar } = wp.blockEditor || wp.editor;
    const { PanelBody, ToggleControl, SelectControl } = wp.components;
    const { __ } = wp.i18n;

    /**
     * Registra il blocco Payment Context
     */
    registerBlockType('born-to-ride/payment-context', {
        title: __('BTR Payment Context', 'born-to-ride-booking'),
        description: __('Mostra la modalità di pagamento selezionata nel checkout', 'born-to-ride-booking'),
        category: 'woocommerce',
        icon: 'money-alt',
        // Limita l'inserimento al Payment Block del checkout
        parent: ['woocommerce/checkout-payment-block'],
        keywords: [
            __('payment', 'born-to-ride-booking'),
            __('checkout', 'born-to-ride-booking'), 
            __('BTR', 'born-to-ride-booking'),
            __('born to ride', 'born-to-ride-booking')
        ],
        usesContext: ['postId', 'postType'],
        supports: {
            align: ['left', 'center', 'right'],
            html: false,
            inserter: true,
            multiple: true,
            reusable: false,
        },
        attributes: {
            alignment: {
                type: 'string',
                default: 'center',
            },
            showIcon: {
                type: 'boolean',
                default: true,
            }
        },

        /**
         * Editor component
         */
        edit: function(props) {
            const { attributes, setAttributes } = props;
            const { alignment, showIcon } = attributes;

            return createElement(Fragment, {},
                // Block Controls (toolbar)
                createElement(BlockControls, {},
                    createElement(AlignmentToolbar, {
                        value: alignment,
                        onChange: (newAlignment) => setAttributes({ alignment: newAlignment })
                    })
                ),

                // Inspector Controls (sidebar)
                createElement(InspectorControls, {},
                    createElement(PanelBody, {
                        title: __('Impostazioni Display', 'born-to-ride-booking'),
                        initialOpen: true
                    },
                        createElement(ToggleControl, {
                            label: __('Mostra Icona', 'born-to-ride-booking'),
                            checked: showIcon,
                            onChange: (value) => setAttributes({ showIcon: value })
                        })
                    )
                ),

                // Preview nell'editor (stile minimale, coerente col sito)
                createElement('div', {
                    className: 'btr-checkout-payment-context-block-preview',
                    style: { textAlign: alignment }
                },
                    createElement('div', {
                        className: 'btr-checkout-payment-context btr-payment-context-preview',
                        style: {
                            background: '#fff',
                            color: 'inherit',
                            padding: '16px',
                            borderRadius: '6px',
                            marginBottom: '16px',
                            border: '1px solid #e5e7eb',
                            boxShadow: 'none'
                        }
                    },
                        createElement('h3', {
                            style: {
                                marginTop: 0,
                                marginBottom: '10px',
                                fontSize: '1.1em',
                                fontWeight: '600'
                            }
                        },
                            showIcon ? createElement('span', { style: { marginRight: '8px' } }, '💳') : null,
                            __('Modalità di Pagamento Selezionata', 'born-to-ride-booking')
                        ),
                        
                        createElement('p', {
                            style: {
                                margin: '6px 0',
                                fontSize: '1em',
                                fontWeight: '600'
                            }
                        }, __('Preview Modalità Pagamento', 'born-to-ride-booking')),
                        
                        createElement('p', {
                            style: { 
                                margin: '6px 0',
                                opacity: '0.9',
                                fontSize: '0.9em'
                            }
                        }, __('👀 Questo blocco mostrerà automaticamente la modalità di pagamento selezionata dal cliente', 'born-to-ride-booking'))
                    )
                )
            );
        },

        /**
         * Save component (rendered server-side)
         */
        save: function() {
            // Return null perché usiamo render_callback server-side
            return null;
        }
    });

    console.log('BTR Payment Context Block: Editor block registrato');

})();
