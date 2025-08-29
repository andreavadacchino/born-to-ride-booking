import './style.scss';   // stile front-end
import Edit from './edit';
import { registerBlockType } from '@wordpress/blocks';
import metadata from '../block.json';

// Registra il blocco solo se non è già registrato
if ( ! wp.blocks.getBlockType( 'btr/checkout-summary' ) ) {
    registerBlockType( metadata.name, {
        ...metadata,
        edit: Edit,
        save: () => null, // il markup viene reso via PHP
    } );
    console.log( '[BTR] Blocco checkout summary registrato nel JavaScript' );
} else {
    console.log( '[BTR] Blocco checkout summary già registrato, skip registrazione JavaScript' );
}