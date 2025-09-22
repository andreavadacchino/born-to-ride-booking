/**
 * BTR Checkout Blocks Payment Context
 * Visualizza modalità pagamento nel checkout a blocchi WooCommerce
 * 
 * @version 1.0.240 - FIXED: TotalsWrapper + Namespace corretto + Backward compatibility
 */

(function() {
    'use strict';

    // ROBUSTO: Diagnostica e aspetta le dipendenze
    function checkDependencies() {
        const deps = {
            'window.wc': !!window.wc,
            'window.wc.blocksCheckout': !!(window.wc && window.wc.blocksCheckout),
            'window.wp': !!window.wp,
            'window.wp.plugins': !!(window.wp && window.wp.plugins),
            'window.wp.element': !!(window.wp && window.wp.element),
            'window.wp.data': !!(window.wp && window.wp.data)
        };

        const missing = Object.entries(deps).filter(([name, available]) => !available).map(([name]) => name);

        if (missing.length === 0) {
            console.log('✅ BTR Payment Context: Tutte le dipendenze sono disponibili');
            return true;
        } else {
            console.warn('⚠️ BTR Payment Context: Dipendenze mancanti:', missing.join(', '));
            return false;
        }
    }

    // Se le dipendenze non sono pronte, aspetta e riprova
    if (!checkDependencies()) {
        console.log('🔄 BTR Payment Context: Aspetto che le dipendenze si carichino...');

        let retryCount = 0;
        const maxRetries = 20; // 10 secondi max

        const waitForDependencies = () => {
            retryCount++;

            if (checkDependencies()) {
                console.log('🚀 BTR Payment Context: Dipendenze caricate, procedo con l\'inizializzazione');
                initializePaymentContext();
            } else if (retryCount < maxRetries) {
                setTimeout(waitForDependencies, 500);
            } else {
                console.error('❌ BTR Payment Context: Timeout - dipendenze non disponibili dopo 10 secondi');
                return;
            }
        };

        setTimeout(waitForDependencies, 500);
        return;
    }

    // Inizializza immediatamente se tutto è disponibile
    initializePaymentContext();

    function initializePaymentContext() {

    // MODERN API: TotalsWrapper per WooCommerce 8.0+ con fallback a ExperimentalOrderMeta
    const { TotalsWrapper, ExperimentalOrderMeta } = window.wc.blocksCheckout;
    const { registerPlugin } = window.wp.plugins;
    const { createElement, useEffect, useState, useRef } = window.wp.element;
    const { useSelect } = window.wp.data;
    const i18n = window.wp.i18n || {};
    const __ = i18n.__ ? i18n.__ : (value) => value;
    const sprintf = i18n.sprintf
        ? i18n.sprintf
        : (template, ...args) => {
            let index = 0;
            return String(template).replace(/%s/g, () => {
                const replacement = args[index];
                index += 1;
                return typeof replacement === 'undefined' ? '' : replacement;
            });
        };

    // Smart SlotFill detection
    const getSlotFillComponent = () => {
        if (TotalsWrapper) {
            console.log('🚀 BTR Payment Context: Using TotalsWrapper (WC 8.0+)');
            return TotalsWrapper;
        }
        if (ExperimentalOrderMeta) {
            console.warn('⚠️ BTR Payment Context: Using ExperimentalOrderMeta (Legacy)');
            return ExperimentalOrderMeta;
        }
        throw new Error('BTR Payment Context: No compatible SlotFill API found');
    };

    /**
     * Componente React per visualizzare il contesto pagamento
     * Riceve cart e extensions come props da SlotFill
     */
    const PaymentContextDisplay = ({ cart, extensions }) => {
        const [paymentMode, setPaymentMode] = useState(null);
        const [preventivoId, setPreventivoId] = useState(null); // FIX: Typo corretto
        const [participantsInfo, setParticipantsInfo] = useState(null);
        const [paymentAmount, setPaymentAmount] = useState(null);
        const [paymentModeLabel, setPaymentModeLabel] = useState(null);
        const [groupAssignments, setGroupAssignments] = useState([]);
        const boxRef = useRef(null);

        // MODERN: Accesso corretto al cart store WooCommerce 8.0+
        const cartData = useSelect((select) => {
            try {
                // Usa il namespace corretto di WooCommerce Blocks
                const cartStore = select('wc/store/cart');
                if (cartStore && typeof cartStore.getCartData === 'function') {
                    return cartStore.getCartData();
                }
            } catch (error) {
                console.warn('BTR Payment Context: Errore accesso cart store:', error);
            }
            return cart; // Fallback ai props
        }, [cart]);

        const normalizeAssignmentsInput = (value) => {
            if (!value) {
                return [];
            }

            if (Array.isArray(value)) {
                return value;
            }

            if (typeof value === 'object') {
                return Object.values(value);
            }

            if (typeof value === 'string') {
                try {
                    const parsed = JSON.parse(value);
                    if (Array.isArray(parsed)) {
                        return parsed;
                    }
                    if (parsed && typeof parsed === 'object') {
                        return Object.values(parsed);
                    }
                } catch (error) {
                    console.warn('BTR Payment Context: impossibile parsare group_assignments', error);
                }
            }

            return [];
        };

        useEffect(() => {
            console.log('BTR Payment Context: Analizzando dati carrello...', cartData);

            const currentCart = cartData || cart;
            let contextFound = false;
            let assignmentsHandled = false;

            const applyAssignments = (value) => {
                const normalized = normalizeAssignmentsInput(value);
                setGroupAssignments((prev) => {
                    const prevLength = Array.isArray(prev) ? prev.length : 0;
                    if (prevLength === normalized.length) {
                        const same = normalized.every((item, index) => {
                            const previous = prev[index];
                            if (!item || !previous) {
                                return item === previous;
                            }
                            return (
                                item.id === previous.id &&
                                item.name === previous.name &&
                                item.shares === previous.shares
                            );
                        });
                        if (same) {
                            return prev;
                        }
                    }
                    return normalized;
                });
                assignmentsHandled = true;
            };

            const applyContext = (context) => {
                if (!context) {
                    return;
                }

                setPaymentMode(context.payment_mode);
                setPreventivoId(context.preventivo_id);
                setParticipantsInfo(context.participants_info);
                setPaymentAmount(context.payment_amount);

                if (context.payment_mode_label) {
                    setPaymentModeLabel(context.payment_mode_label);
                }

                if (context.group_assignments && !assignmentsHandled) {
                    applyAssignments(context.group_assignments);
                }

                contextFound = true;
            };

            if (currentCart && currentCart.items && currentCart.items.length > 0) {
                currentCart.items.forEach((item) => {
                    if (!contextFound && item.extensions) {
                        const ext = item.extensions;
                        const context = ext['btr_payment_context'] || ext['btr-payment-context'] || null;
                        if (context) {
                            applyContext(context);
                        }
                    }

                    if (item.meta_data && Array.isArray(item.meta_data)) {
                        item.meta_data.forEach((meta) => {
                            if (meta.key === '_btr_payment_mode' && !contextFound) {
                                setPaymentMode(meta.value);
                            }
                            if (meta.key === '_btr_preventivo_id' && !contextFound) {
                                setPreventivoId(meta.value);
                            }
                            if (meta.key === '_btr_payment_amount' && !contextFound) {
                                setPaymentAmount(meta.value);
                            }
                            if (meta.key === '_btr_participants_info' && !contextFound) {
                                setParticipantsInfo(meta.value);
                            }
                            if (meta.key === '_btr_group_assignments' && !assignmentsHandled) {
                                applyAssignments(meta.value);
                            }
                        });
                    }

                    if (!contextFound && item.item_data && Array.isArray(item.item_data)) {
                        item.item_data.forEach((data) => {
                            if (data.key && data.key.includes('Modalità Pagamento')) {
                                const match = data.value && data.value.match ? data.value.match(/>([^<]+)</) : null;
                                if (match) {
                                    setPaymentMode(match[1].toLowerCase());
                                }
                            }
                        });
                    }
                });
            }

            if (!contextFound && window.btrPaymentContext) {
                console.log('BTR Payment Context: Usando dati localizzati:', window.btrPaymentContext);
                applyContext(window.btrPaymentContext);
            }

            if (!assignmentsHandled) {
                if (window.btrPaymentContext && window.btrPaymentContext.group_assignments) {
                    applyAssignments(window.btrPaymentContext.group_assignments);
                } else {
                    setGroupAssignments((prev) => (Array.isArray(prev) && prev.length === 0 ? prev : []));
                }
            }
        }, [cart, cartData]);

        // Relocation: posiziona il box in un punto sempre visibile
        // Preferenza: sopra i metodi di pagamento; fallback: sotto le opzioni di spedizione
        useEffect(() => {
            try {
                const el = boxRef.current || document.getElementById('btr-payment-context-box') || document.querySelector('.btr-checkout-payment-context');
                if (!el) return;

                // 1) Prova a spostarlo sopra i metodi di pagamento
                const paymentSelectors = [
                    '[data-block-name="woocommerce/checkout-payment-methods-block"]',
                    '.wc-block-checkout__payment-methods',
                    '.wc-block-components-payment-methods'
                ];
                let placed = false;
                for (let i = 0; i < paymentSelectors.length; i++) {
                    const target = document.querySelector(paymentSelectors[i]);
                    if (target && target.parentNode) {
                        if (el !== target) {
                            target.parentNode.insertBefore(el, target);
                            console.log('BTR Payment Context: Box spostato sopra i metodi di pagamento usando selector:', paymentSelectors[i]);
                        }
                        placed = true;
                        break;
                    }
                }

                if (placed) return;

                // 2) Fallback: posizionalo subito sotto il blocco di spedizione (sempre visibile)
                const shippingSelectors = [
                    '[data-block-name="woocommerce/checkout-shipping-methods-block"]',
                    '.wc-block-checkout__shipping-methods',
                    '.wc-block-components-shipping-rates',
                    '.wc-block-components-shipping-rates-control'
                ];
                for (let i = 0; i < shippingSelectors.length; i++) {
                    const target = document.querySelector(shippingSelectors[i]);
                    if (target && target.parentNode) {
                        if (el.nextSibling !== target.nextSibling) {
                            target.parentNode.insertBefore(el, target.nextSibling);
                            console.log('BTR Payment Context: Box posizionato sotto le opzioni di spedizione usando selector:', shippingSelectors[i]);
                        }
                        placed = true;
                        break;
                    }
                }

                // 3) Ultimo fallback: lascia il box dov'è (TotalsWrapper)
            } catch (e) {
                console.warn('BTR Payment Context: Errore durante relocation del box:', e);
            }
        }, [paymentMode, paymentAmount, participantsInfo, preventivoId, groupAssignments]);

        // Se non c'è modalità di pagamento, non mostrare nulla
        if (!paymentMode) {
            console.log('BTR Payment Context: Nessuna modalità pagamento trovata');
            return null;
        }

        console.log('BTR Payment Context: Rendering con modalità:', paymentMode);

        // PULITO: Rimosso debug temporaneo, ora usa SlotFill appropriato

        // Stile minimale: niente gradient, usa palette tema
        let computedModeLabel = paymentMode ? String(paymentMode) : '';
        let icon = '💳';

        switch(paymentMode) {
            case 'caparro':
            case 'pagamento caparra (30%)':
                computedModeLabel = __('Pagamento Caparra (30%)', 'born-to-ride-booking');
                icon = '💰';
                break;
            case 'gruppo':
            case 'pagamento di gruppo':
                computedModeLabel = __('Pagamento di Gruppo', 'born-to-ride-booking');
                icon = '👥';
                break;
            case 'completo':
            case 'pagamento completo':
                computedModeLabel = __('Pagamento Completo', 'born-to-ride-booking');
                icon = '✅';
                break;
        }

        const modeLabel = paymentModeLabel || computedModeLabel;

        // Determina classi specifiche per modalità per styling coerente
        let modeClass = '';
        const normalizedMode = (paymentMode || '').toString().toLowerCase();
        if (normalizedMode.includes('caparra') || normalizedMode === 'caparro') {
            modeClass = 'btr-payment-context-caparro';
        } else if (normalizedMode.includes('gruppo')) {
            modeClass = 'btr-payment-context-gruppo';
        } else if (normalizedMode.includes('completo')) {
            modeClass = 'btr-payment-context-completo';
        } else if (normalizedMode.includes('saldo')) {
            modeClass = 'btr-payment-context-saldo';
        }

        const getParticipantsValue = (val) => {
            if (!val) return '';
            try {
                if (typeof val === 'object' && !Array.isArray(val)) {
                    const total = val.total ?? null;
                    const breakdown = val.breakdown ?? '';
                    if (total !== null) {
                        return `${total}${breakdown ? ` (${breakdown})` : ''}`;
                    }
                }
                if (Array.isArray(val)) {
                    return val.join(', ');
                }
                return String(val);
            } catch (error) {
                return '';
            }
        };

        const participantsValue = getParticipantsValue(participantsInfo);
        const numericAmount = Number(paymentAmount);
        const hasAmount = paymentAmount !== null && paymentAmount !== undefined && paymentAmount !== '' && !Number.isNaN(numericAmount);
        const formattedAmount = hasAmount
            ? numericAmount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
            : '';
        const hasParticipants = Boolean(participantsValue);
        const assignmentsForDisplay = (Array.isArray(groupAssignments) ? groupAssignments : []).map((assignment, index) => {
            if (!assignment || typeof assignment !== 'object') {
                return null;
            }

            const rawName = assignment.name || assignment.full_name || assignment.label;
            const name = typeof rawName === 'string' ? rawName.trim() : '';
            if (!name) {
                return null;
            }

            const rawShares = assignment.shares ?? assignment.quotes ?? assignment.quantity ?? 1;
            let shares = parseInt(rawShares, 10);
            if (Number.isNaN(shares) || shares < 1) {
                shares = 1;
            }

            const shareLabel = shares === 1 ? __('quota', 'born-to-ride-booking') : __('quote', 'born-to-ride-booking');

            return {
                key: assignment.id || assignment.participant_id || `${name}-${shares}-${index}`,
                name,
                shares,
                shareLabel
            };
        }).filter(Boolean);
        const hasAssignments = assignmentsForDisplay.length > 0;

        const currencySettings = window.wcSettings && typeof window.wcSettings.getSetting === 'function'
            ? window.wcSettings.getSetting('currency', {})
            : {};
        const currencySymbol = currencySettings.symbol || '€';
        const formattedAmountDisplay = hasAmount ? `${currencySymbol}${formattedAmount}` : '';

        const containerStyle = {
            marginBottom: '24px',
            position: 'relative'
        };

        const eyebrowLabel = __('Modalità pagamento', 'born-to-ride-booking');
        const preventivoLabel = preventivoId ? sprintf(__('Preventivo #%s', 'born-to-ride-booking'), preventivoId) : null;
        const participantsLabel = __('Partecipanti', 'born-to-ride-booking');
        const amountLabel = __('Importo dovuto', 'born-to-ride-booking');
        const payersLabel = __('Paganti', 'born-to-ride-booking');
        const organizerNotice = __("Sei l'organizzatore: nessun pagamento è richiesto adesso.", 'born-to-ride-booking');
        const finalModeLabel = modeLabel || computedModeLabel || eyebrowLabel;
        const isOrganizer = normalizedMode.includes('gruppo') && !Number.isNaN(numericAmount) && numericAmount === 0;

        return createElement(
            'div',
            { 
                id: 'btr-payment-context-box',
                ref: boxRef,
                className: `btr-checkout-payment-context btr-checkout-payment-context-block ${modeClass}`,
                style: containerStyle,
                'data-payment-mode': paymentMode || ''
            },
            [
                createElement('div', {
                    key: 'header',
                    className: 'btr-checkout-payment-context__header'
                }, [
                    createElement('div', {
                        key: 'identity',
                        className: 'btr-checkout-payment-context__identity'
                    }, [
                        createElement('span', {
                            key: 'icon',
                            className: 'btr-checkout-payment-context__icon',
                            'aria-hidden': 'true'
                        }, icon),
                        createElement('div', {
                            key: 'headline',
                            className: 'btr-checkout-payment-context__headline'
                        }, [
                            createElement('span', {
                                key: 'eyebrow',
                                className: 'btr-checkout-payment-context__eyebrow'
                            }, eyebrowLabel),
                            createElement('span', {
                                key: 'mode',
                                className: 'btr-payment-mode'
                            }, finalModeLabel)
                        ])
                    ]),
                    preventivoLabel ? createElement('span', {
                        key: 'preventivo',
                        className: 'btr-checkout-payment-context__pill'
                    }, preventivoLabel) : null
                ].filter(Boolean)),

                isOrganizer ? createElement('div', {
                    key: 'notice',
                    className: 'btr-checkout-payment-context__notice'
                }, organizerNotice) : null,

                (hasParticipants || hasAmount || hasAssignments) ? createElement('div', {
                    key: 'meta',
                    className: 'btr-checkout-payment-context__meta'
                }, [
                    hasParticipants ? createElement('div', {
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
                            }, participantsLabel),
                            createElement('span', {
                                key: 'participants-value',
                                className: 'btr-checkout-payment-context__meta-value'
                            }, participantsValue)
                        ])
                    ]) : null,
                    hasAmount ? createElement('div', {
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
                            }, amountLabel),
                            createElement('span', {
                                key: 'amount-value',
                                className: 'btr-checkout-payment-context__meta-value'
                            }, formattedAmountDisplay)
                        ])
                    ]) : null,
                    hasAssignments ? createElement('div', {
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
                            }, payersLabel),
                            createElement('div', {
                                key: 'payers-chips',
                                className: 'btr-checkout-payment-context__chips'
                            }, assignmentsForDisplay.map((assignment) => createElement('span', {
                                key: assignment.key,
                                className: 'btr-checkout-payment-context__chip'
                            }, [
                                assignment.name,
                                createElement('span', {
                                    key: 'quantity',
                                    className: 'btr-checkout-payment-context__chip-quantity'
                                }, `(${assignment.shares} ${assignment.shareLabel})`)
                            ])))
                        ])
                    ]) : null
                ].filter(Boolean)) : null
            ].filter(Boolean) // Rimuove elementi null/undefined
        );
    };

    /**
     * TEST: 3 approcci per posizionamento ottimale nel checkout Gutenberg
     */
    const render = () => {
        try {
            const SlotFillComponent = getSlotFillComponent();

            // 🧪 TEST 1: TotalsWrapper SENZA slotName (posizionamento automatico)
            if (TotalsWrapper && SlotFillComponent === TotalsWrapper) {
                console.log('🧪 TEST 1: TotalsWrapper senza slotName - posizionamento automatico');
                return createElement(SlotFillComponent, {
                    // Rimosso slotName per posizionamento automatico
                }, createElement(PaymentContextDisplay, null));
            }

            // Legacy API: ExperimentalOrderMeta
            console.log('🧪 FALLBACK: ExperimentalOrderMeta');
            return createElement(SlotFillComponent, null,
                   createElement(PaymentContextDisplay, null));

        } catch (error) {
            console.error('BTR Payment Context: Errore rendering:', error);
            return null;
        }
    };

    /**
     * 🧪 TEST 2: Registrazione con shipping-methods-block
     */
    const renderShippingTest = () => {
        try {
            const SlotFillComponent = getSlotFillComponent();

            if (TotalsWrapper && SlotFillComponent === TotalsWrapper) {
                console.log('🧪 TEST 2: TotalsWrapper con shipping-methods-block');
                return createElement(SlotFillComponent, {
                    slotName: 'woocommerce/checkout-shipping-methods-block'
                }, createElement(PaymentContextDisplay, null));
            }

            return createElement(SlotFillComponent, null,
                   createElement(PaymentContextDisplay, null));

        } catch (error) {
            console.error('BTR Payment Context: Errore rendering shipping test:', error);
            return null;
        }
    };

    // 🧪 SISTEMA DI TEST MULTIPLO per trovare posizionamento ottimale

    // TEST 1: TotalsWrapper senza slotName (posizionamento automatico)
    registerPlugin('btr-payment-context-test1', {
        render: render,
        scope: 'woocommerce-checkout'
    });

    // Aggiungi logging per identificare dove si posiziona
    setTimeout(() => {
        const box = document.getElementById('btr-payment-context-box');
        if (box) {
            console.log('📍 TEST 1 POSIZIONE:', {
                'parent_class': box.parentNode?.className,
                'parent_tag': box.parentNode?.tagName,
                'previous_sibling': box.previousElementSibling?.className,
                'next_sibling': box.nextElementSibling?.className,
                'position_in_dom': Array.from(box.parentNode?.children || []).indexOf(box)
            });
        }
    }, 3000);

    // 🧪 TEST 2: Shipping methods block (commentato per ora)
    // setTimeout(() => {
    //     registerPlugin('btr-payment-context-test2', {
    //         render: renderShippingTest,
    //         scope: 'woocommerce-checkout'
    //     });
    //     console.log('🧪 TEST 2: Shipping methods block attivato');
    // }, 5000);

        console.log('🧪 BTR Payment Context v1.0.244: Sistema di test posizionamento attivato');
        console.log('📋 TEST PLAN:');
        console.log('  1. TotalsWrapper senza slotName (automatico)');
        console.log('  2. Shipping methods block (on-demand)');
        console.log('  3. Hook WordPress fallback (TBD)');
        console.log('⏰ Controllare logs tra 3-5 secondi per posizione...');
    }

})();
