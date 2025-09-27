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
                        className: 'btr-checkout-payment-context btr-payment-context-preview'
                    }, [
                        createElement('div', {
                            key: 'header',
                            className: 'btr-checkout-payment-context__header'
                        }, [
                            createElement('div', {
                                key: 'identity',
                                className: 'btr-checkout-payment-context__identity'
                            }, [
                                showIcon ? createElement('span', {
                                    key: 'icon',
                                    className: 'btr-checkout-payment-context__icon',
                                    'aria-hidden': 'true'
                                }, '💳') : null,
                                createElement('div', {
                                    key: 'headline',
                                    className: 'btr-checkout-payment-context__headline'
                                }, [
                                    createElement('span', {
                                        key: 'eyebrow',
                                        className: 'btr-checkout-payment-context__eyebrow'
                                    }, __('Modalità pagamento', 'born-to-ride-booking')),
                                    createElement('span', {
                                        key: 'mode',
                                        className: 'btr-payment-mode'
                                    }, __('Preview Modalità Pagamento', 'born-to-ride-booking'))
                                ])
                            ]),
                            createElement('span', {
                                key: 'pill',
                                className: 'btr-checkout-payment-context__pill'
                            }, __('Preventivo #12345', 'born-to-ride-booking'))
                        ].filter(Boolean)),

                        createElement('div', {
                            key: 'notice',
                            className: 'btr-checkout-payment-context__notice'
                        }, __('Messaggio informativo per i pagamenti speciali o l\'organizzatore.', 'born-to-ride-booking')),

                        createElement('div', {
                            key: 'meta',
                            className: 'btr-checkout-payment-context__meta'
                        }, [
                            createElement('div', {
                                key: 'participants',
                                className: 'btr-checkout-payment-context__meta-item'
                            }, [
                                createElement('span', {
                                    key: 'participants-icon',
                                    className: 'btr-checkout-payment-context__meta-icon',
                                    'aria-hidden': 'true'
                                }, '👥'),
                                createElement('div', {
                                    key: 'participants-copy',
                                    className: 'btr-checkout-payment-context__meta-copy'
                                }, [
                                    createElement('span', {
                                        key: 'participants-label',
                                        className: 'btr-checkout-payment-context__meta-label'
                                    }, __('Partecipanti', 'born-to-ride-booking')),
                                    createElement('span', {
                                        key: 'participants-value',
                                        className: 'btr-checkout-payment-context__meta-value'
                                    }, __('4 (2 adulti, 2 bambini)', 'born-to-ride-booking'))
                                ])
                            ]),
                            createElement('div', {
                                key: 'amount',
                                className: 'btr-checkout-payment-context__meta-item'
                            }, [
                                createElement('span', {
                                    key: 'amount-icon',
                                    className: 'btr-checkout-payment-context__meta-icon',
                                    'aria-hidden': 'true'
                                }, '💰'),
                                createElement('div', {
                                    key: 'amount-copy',
                                    className: 'btr-checkout-payment-context__meta-copy'
                                }, [
                                    createElement('span', {
                                        key: 'amount-label',
                                        className: 'btr-checkout-payment-context__meta-label'
                                    }, __('Importo dovuto', 'born-to-ride-booking')),
                                    createElement('span', {
                                        key: 'amount-value',
                                        className: 'btr-checkout-payment-context__meta-value'
                                    }, '€ 120,00')
                                ])
                            ]),
                            createElement('div', {
                                key: 'payers',
                                className: 'btr-checkout-payment-context__meta-item'
                            }, [
                                createElement('span', {
                                    key: 'payers-icon',
                                    className: 'btr-checkout-payment-context__meta-icon',
                                    'aria-hidden': 'true'
                                }, '💳'),
                                createElement('div', {
                                    key: 'payers-copy',
                                    className: 'btr-checkout-payment-context__meta-copy'
                                }, [
                                    createElement('span', {
                                        key: 'payers-label',
                                        className: 'btr-checkout-payment-context__meta-label'
                                    }, __('Paganti', 'born-to-ride-booking')),
                                    createElement('div', {
                                        key: 'payers-chips',
                                        className: 'btr-checkout-payment-context__chips'
                                    }, [
                                        createElement('span', {
                                            key: 'payer-1',
                                            className: 'btr-checkout-payment-context__chip'
                                        }, [
                                            'Mario Rossi',
                                            createElement('span', {
                                                key: 'payer-1-quantity',
                                                className: 'btr-checkout-payment-context__chip-quantity'
                                            }, __('(2 quote)', 'born-to-ride-booking'))
                                        ]),
                                        createElement('span', {
                                            key: 'payer-2',
                                            className: 'btr-checkout-payment-context__chip'
                                        }, [
                                            'Luca Bianchi',
                                            createElement('span', {
                                                key: 'payer-2-quantity',
                                                className: 'btr-checkout-payment-context__chip-quantity'
                                            }, __('(1 quota)', 'born-to-ride-booking'))
                                        ])
                                    ])
                                ])
                            ])
                        ])
                    ])
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
