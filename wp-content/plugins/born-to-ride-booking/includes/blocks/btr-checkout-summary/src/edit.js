/* global wp */
import { __ } from '@wordpress/i18n';
import { Placeholder } from '@wordpress/components';
import { useBlockProps } from '@wordpress/block-editor';
import './style.scss';

export default function Edit( { attributes } ) {
    const blockProps = useBlockProps( {
        className: 'wc-block-checkout__totals-block'
    } );

    return (
        <div { ...blockProps }>
            <Placeholder
                icon="cart"
                label={ __( 'BTR Riepilogo Ordine', 'born-to-ride-booking' ) }
                className="wc-block-btr-checkout-summary"
            >
                <p>
                    { __( 
                        'Questo blocco mostra un riepilogo dettagliato dell\'ordine con i dati del preventivo BTR.', 
                        'born-to-ride-booking' 
                    ) }
                </p>
                <p>
                    <small>
                        { __( 
                            'Il contenuto verrà renderizzato dinamicamente nel checkout.', 
                            'born-to-ride-booking' 
                        ) }
                    </small>
                </p>
            </Placeholder>
        </div>
    );
}