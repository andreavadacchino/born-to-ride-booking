/**
 * BTR Checkout Blocks Payment Context
 * Visualizza modalità pagamento nel checkout a blocchi WooCommerce
 * 
 * @version 1.0.240 - FIXED: TotalsWrapper + Namespace corretto + Backward compatibility
 */

(function() {
    'use strict';

    // Verifica che le dipendenze siano disponibili
    if (!window.wc || !window.wc.blocksCheckout || !window.wp || !window.wp.plugins) {
        console.error('BTR Payment Context: Dipendenze mancanti - WooCommerce Blocks o WordPress plugins non disponibili');
        return;
    }

    // MODERN API: TotalsWrapper per WooCommerce 8.0+ con fallback a ExperimentalOrderMeta
    const { TotalsWrapper, ExperimentalOrderMeta } = window.wc.blocksCheckout;
    const { registerPlugin } = window.wp.plugins;
    const { createElement, useEffect, useState } = window.wp.element;
    const { useSelect } = window.wp.data;

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

        useEffect(() => {
            console.log('BTR Payment Context: Analizzando dati carrello...', cartData);

            // Usa cartData se disponibile, altrimenti fallback ai props
            const currentCart = cartData || cart;
            
            if (currentCart && currentCart.items && currentCart.items.length > 0) {
                // Cerca nei cart items
                currentCart.items.forEach(item => {
                    console.log('BTR Payment Context: Analizzando item:', item);
                    
                    // Cerca nei metadata del cart item (Store API extension)
                    if (item.extensions && item.extensions.btr_payment_context) {
                        const context = item.extensions.btr_payment_context;
                        console.log('BTR Payment Context: Trovato in extensions:', context);
                        setPaymentMode(context.payment_mode);
                        setPreventivoId(context.preventivo_id);
                        setParticipantsInfo(context.participants_info);
                        setPaymentAmount(context.payment_amount);
                        return;
                    }
                    
                    // Cerca nei meta_data (cart item metadata)
                    if (item.meta_data && Array.isArray(item.meta_data)) {
                        console.log('BTR Payment Context: Analizzando meta_data:', item.meta_data);
                        item.meta_data.forEach(meta => {
                            if (meta.key === '_btr_payment_mode') {
                                console.log('BTR Payment Context: Trovato payment_mode:', meta.value);
                                setPaymentMode(meta.value);
                            }
                            if (meta.key === '_btr_preventivo_id') {
                                console.log('BTR Payment Context: Trovato preventivo_id:', meta.value);
                                setPreventivoId(meta.value);
                            }
                            if (meta.key === '_btr_payment_amount') {
                                console.log('BTR Payment Context: Trovato payment_amount:', meta.value);
                                setPaymentAmount(meta.value);
                            }
                            if (meta.key === '_btr_participants_info') {
                                console.log('BTR Payment Context: Trovato participants_info:', meta.value);
                                setParticipantsInfo(meta.value);
                            }
                        });
                    }

                    // Cerca anche negli item_data per compatibilità (display data)
                    if (item.item_data && Array.isArray(item.item_data)) {
                        console.log('BTR Payment Context: Analizzando item_data:', item.item_data);
                        item.item_data.forEach(data => {
                            if (data.key && data.key.includes('Modalità Pagamento')) {
                                // Estrai la modalità dal value HTML
                                const match = data.value.match(/>([^<]+)</);
                                if (match) {
                                    console.log('BTR Payment Context: Trovato in item_data:', match[1]);
                                    setPaymentMode(match[1].toLowerCase());
                                }
                            }
                        });
                    }
                });
            }

            // Fallback: cerca nei dati localizzati
            if (!paymentMode && window.btrPaymentContext) {
                console.log('BTR Payment Context: Usando dati localizzati:', window.btrPaymentContext);
                setPaymentMode(window.btrPaymentContext.payment_mode);
                setPreventivoId(window.btrPaymentContext.preventivo_id);
                setParticipantsInfo(window.btrPaymentContext.participants_info);
                setPaymentAmount(window.btrPaymentContext.payment_amount);
            }
        }, [cart, cartData]);

        // Se non c'è modalità di pagamento, non mostrare nulla
            if (!paymentMode) {
                console.log('BTR Payment Context: Nessuna modalità pagamento trovata');
                return null;
            }

        console.log('BTR Payment Context: Rendering con modalità:', paymentMode);

        // Stile minimale: niente gradient, usa palette tema
        let gradientClass = '';
        let modeLabel = paymentMode;
        let icon = '💳';
        let containerStyle = {
            background: '#fff',
            color: 'inherit',
            padding: '16px',
            borderRadius: '6px',
            marginBottom: '24px',
            border: '1px solid #e5e7eb',
            boxShadow: 'none',
            position: 'relative'
        };

        switch(paymentMode) {
            case 'caparro':
            case 'pagamento caparra (30%)':
                modeLabel = 'Pagamento Caparra (30%)';
                icon = '💰';
                break;
            case 'gruppo':
            case 'pagamento di gruppo':
                modeLabel = 'Pagamento di Gruppo';
                icon = '👥';
                break;
            case 'completo':
            case 'pagamento completo':
                modeLabel = 'Pagamento Completo';
                icon = '✅';
                break;
        }

        const formatParticipants = (val) => {
            if (!val) return null;
            try {
                // Gestisci oggetto { total, breakdown }
                if (typeof val === 'object' && !Array.isArray(val)) {
                    const total = val.total ?? null;
                    const breakdown = val.breakdown ?? '';
                    if (total !== null) {
                        return `👥 Partecipanti: ${total}${breakdown ? ` (${breakdown})` : ''}`;
                    }
                }
                // Gestisci array
                if (Array.isArray(val)) {
                    return `👥 Partecipanti: ${val.join(', ')}`;
                }
                // String/number
                return `👥 Partecipanti: ${String(val)}`;
            } catch (e) {
                return null;
            }
        };
        const participantsText = formatParticipants(participantsInfo);

        return createElement(
            'div',
            { 
                className: `btr-checkout-payment-context ${gradientClass}`,
                style: containerStyle
            },
            [
                createElement('h3', {
                    key: 'title',
                    style: {
                        marginTop: 0,
                        marginBottom: '10px',
                        fontSize: '1.1em',
                        fontWeight: '600'
                    }
                }, `${icon} Modalità di Pagamento Selezionata`),
                
                createElement('p', {
                    key: 'mode',
                    style: {
                        margin: '6px 0',
                        fontSize: '1em',
                        fontWeight: '600'
                    }
                }, modeLabel),
                
                participantsText && createElement('p', {
                    key: 'participants',
                    style: { margin: '6px 0' }
                }, participantsText),
                
                paymentAmount && createElement('p', {
                    key: 'amount',
                    style: { 
                        margin: '6px 0',
                        fontSize: '1em',
                        fontWeight: '600'
                    }
                }, `💰 Importo: €${paymentAmount}`),
                
                preventivoId && createElement('p', {
                    key: 'preventivo',
                    style: { 
                        margin: '6px 0',
                        opacity: '0.9'
                    }
                }, `📋 Preventivo #${preventivoId}`)
            ].filter(Boolean) // Rimuove elementi null/undefined
        );
    };

    /**
     * Componente wrapper che registra il plugin con modern/legacy SlotFill
     */
    const render = () => {
        try {
            const SlotFillComponent = getSlotFillComponent();
            
            // Modern API: TotalsWrapper with slotName
            if (TotalsWrapper && SlotFillComponent === TotalsWrapper) {
                return createElement(SlotFillComponent, {
                    slotName: 'woocommerce/checkout-order-summary-block'
                }, createElement(PaymentContextDisplay, null));
            }
            
            // Legacy API: ExperimentalOrderMeta
            return createElement(SlotFillComponent, null, 
                   createElement(PaymentContextDisplay, null));
                   
        } catch (error) {
            console.error('BTR Payment Context: Errore rendering:', error);
            return null;
        }
    };

    // Registra il plugin per il checkout WooCommerce
    registerPlugin('btr-payment-context', {
        render: render,
        scope: 'woocommerce-checkout'
    });

    console.log('BTR Payment Context v1.0.240: Plugin registrato con SlotFill moderno/legacy compatibility');

})();
