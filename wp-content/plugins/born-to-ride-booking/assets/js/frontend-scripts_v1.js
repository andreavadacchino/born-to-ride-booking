/**
 * Script di prenotazione BTR
 * Versione migliorata con best practice di sviluppo
 * 
 * Questo script gestisce il form di prenotazione per un sistema di alloggi,
 * permettendo la selezione di date, camere, e la raccolta di dati dei partecipanti.
 */
jQuery(document).ready(function ($) {
  // ======================================================================
  // INIZIALIZZAZIONE VARIABILI E SELEZIONE ELEMENTI DOM
  // ======================================================================
  
  /**
   * Selezione degli elementi principali del DOM
   * Utilizziamo il caching dei selettori per migliorare le performance
   */
  const form = $('#btr-booking-form');
  const numPeopleSection = $('#btr-num-people-section');
  const roomTypesSection = $('#btr-room-types-section');
  const roomTypesContainer = $('#btr-room-types-container');
  const proceedButton = $('#btr-proceed');
  const customerSection = $('#btr-customer-section');
  const createQuoteButton = $('#btr-create-quote');
  
  /**
   * Campi per il numero di partecipanti
   * Utilizziamo nomi più descrittivi per migliorare la leggibilità
   */
  const numAdultsField = $('#btr_num_adults');
  const numInfantsField = $('#btr_num_infants');
  const numChildF1Field = $('#btr_num_child_f1');
  const numChildF2Field = $('#btr_num_child_f2');
  
  /**
   * Elementi di visualizzazione
   */
  const totalCapacityDisplay = $('#btr-total-capacity');
  const requiredCapacityDisplay = $('#btr-required-capacity');
  const totalPriceDisplay = $('#btr-total-price');
  const bookingResponse = $('#btr-booking-response');
  
  /**
   * Variabili di stato
   * Inizializzate con valori predefiniti
   */
  let requiredCapacity = 0;
  let selectedCapacity = 0;
  let totalPrice = 0;
  
  /**
   * Recupero della tipologia di prenotazione
   */
  const tipologiaPrenotazione = form.find('input[name="btr_tipologia_prenotazione"]').val();
  console.log('Tipologia Prenotazione:', tipologiaPrenotazione);
  
  // ======================================================================
  // ICONE SVG PER LE TIPOLOGIE DI CAMERA
  // ======================================================================
  
  /**
   * Icone SVG per le diverse tipologie di camera
   * Estratte in costanti per migliorare la leggibilità e la manutenibilità
   */
  const icona_singola = '<svg id="Raggruppa_26" data-name="Raggruppa 26" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="67.972" height="38.255" viewBox="0 0 67.972 38.255"><defs><clipPath id="clip-path"><rect id="Rettangolo_33" data-name="Rettangolo 33" width="67.972" height="38.255" fill="#0097c5"/></clipPath></defs><g id="Raggruppa_25" data-name="Raggruppa 25"><path id="Tracciato_37" data-name="Tracciato 37" d="M0,2.53C.818-.163,4.263-1.013,5.874,1.5a6.341,6.341,0,0,1,.5,1.092V12.732A7.468,7.468,0,0,1,7.8,11.967c1.421-.485,7.051-.451,8.716-.278a5.184,5.184,0,0,1,4.333,3.428,6.113,6.113,0,0,1,3.378-1.331H58.75A5.735,5.735,0,0,1,61.6,14.852c-.657-4.4,4.852-6.013,6.374-1.722V37.51c-.265.319-.421.583-.871.654a28.632,28.632,0,0,1-4.379.014c-.38-.034-1.122-.3-1.122-.735V32.872H6.372v4.571c0,.437-.742.7-1.122.735a28.632,28.632,0,0,1-4.379-.014C.421,38.093.265,37.829,0,37.51ZM2.124,36.052H4.248V2.123H2.124Zm63.724,0V13.461c0-.924-2.124-.924-2.124,0V36.052Zm-46.731-15.9V16.376a9.647,9.647,0,0,0-.393-.932,3.513,3.513,0,0,0-2.227-1.62,45.757,45.757,0,0,0-7.01-.038,3.285,3.285,0,0,0-2.669,1.576,7.705,7.705,0,0,0-.445,1.013v3.776ZM61.6,26.512V18.5c0-.994-1.789-2.629-2.853-2.585l-34.391,0c-1.16-.193-3.114,1.526-3.114,2.589v8.016Zm-42.483-4.24H6.372v8.48H61.6v-2.12H19.847a1.651,1.651,0,0,1-.73-.729Z" transform="translate(0 -0.001)" fill="#0097c5"/></g></svg>';
  const icona_doppia = '<svg id="Raggruppa_22" data-name="Raggruppa 22" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="55" height="50.651" viewBox="0 0 55 50.651"><defs><clipPath id="clip-path"><rect id="Rettangolo_24" data-name="Rettangolo 24" width="55" height="50.651" fill="#0097c5"/></clipPath></defs><g id="Raggruppa_21" data-name="Raggruppa 21"><path id="Tracciato_35" data-name="Tracciato 35" d="M55,32.061v14.9A4.408,4.408,0,0,1,47.229,49a5.013,5.013,0,0,1-.91-1.985V44.927H8.812v2.091A5.011,5.011,0,0,1,7.9,49,4.408,4.408,0,0,1,.132,46.964c.311-4.812-.416-10.153,0-14.9a12.943,12.943,0,0,1,3.32-7.132l0-18.121A7.643,7.643,0,0,1,10.148,0H44.663a7.628,7.628,0,0,1,6.8,6.809V24.715A12.905,12.905,0,0,1,55,32.061m-5.68-9.006V6.706c0-1.978-2.837-4.631-4.871-4.561l-34.285.016C8.334,2.241,5.6,4.8,5.6,6.6V23.27a7.043,7.043,0,0,1,2.238-1.084c.126-1.749-.234-3.652.286-5.343a5.747,5.747,0,0,1,4.92-3.977c3.5-.245,7.289.184,10.817.016a6.075,6.075,0,0,1,3.67,2.232c.235.04.466-.48.629-.634a6.244,6.244,0,0,1,3.119-1.6c3.543.19,7.419-.293,10.923-.015a5.816,5.816,0,0,1,4.841,4.055c.485,1.659.13,3.551.258,5.264ZM26.494,21.34V18.07a3.767,3.767,0,0,0-2.781-3.008c-3.39.1-7.1-.3-10.459-.052a3.51,3.51,0,0,0-2.876,1.947,8.707,8.707,0,0,0-.387,1.113v3.484c.66-.064,1.432-.194,2.085-.219,4.761-.185,9.645.148,14.418,0m18.647.214V18.07c0-1.495-1.835-2.953-3.264-3.06-3.335-.249-7,.172-10.372.033a3.853,3.853,0,0,0-2.868,2.92V21.34c4.805.151,9.733-.2,14.525,0,.619.025,1.352.158,1.978.219m7.716,14.8V32.222a11.478,11.478,0,0,0-1.414-4.053,9.946,9.946,0,0,0-8.387-4.689c-10.121-.534-20.7.413-30.873,0-4.469-.339-9.908,4.209-9.908,8.743V36.35h29.1a1.225,1.225,0,0,1,1.1,1.044c.031.328-.342,1.1-.674,1.1H2.275v4.288H52.857V38.494H41.765a1.555,1.555,0,0,1-.615-.563,1.162,1.162,0,0,1,.936-1.581ZM6.669,44.927H2.275V46.7c0,.037.231.6.278.686a2.252,2.252,0,0,0,3.777.154,6.054,6.054,0,0,0,.339-.733Zm46.188,0H48.463V46.8a6.054,6.054,0,0,0,.339.733,2.252,2.252,0,0,0,3.777-.154c.048-.091.278-.65.278-.686Z" transform="translate(0)" fill="#0097c5"/><path id="Tracciato_36" data-name="Tracciato 36" d="M251.372,255.243a1.062,1.062,0,1,1-1.062-1.063,1.063,1.063,0,0,1,1.062,1.063" transform="translate(-213.548 -217.774)" fill="#0097c5"/></g></svg>';
  // Nota: le altre icone SVG sono state omesse per brevità
  const icona_tripla = '<svg>...</svg>'; // Contenuto omesso per brevità
  const icona_quadrupla = '<svg>...</svg>'; // Contenuto omesso per brevità
  const icona_quintupla = '<svg>...</svg>'; // Contenuto omesso per brevità
  const icona_condivisa = '<svg id="Raggruppa_28" data-name="Raggruppa 28" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="55.295" height="55.184" viewBox="0 0 55.295 55.184"><defs><clipPath id="clip-path"><rect id="Rettangolo_34" data-name="Rettangolo 34" width="55.295" height="55.184" fill="#72c3dc"/></clipPath></defs><g id="Raggruppa_27" data-name="Raggruppa 27"><path id="Tracciato_38" data-name="Tracciato 38" d="M4.446,7.558A6.541,6.541,0,0,1,5.2,7.2a32.537,32.537,0,0,1,7.319-.2c1.336.181,3.12,1.64,3.12,3.054V12.1H38.373c-.285-3.026,3.431-3.918,4.489-1.047h7.986V1.8a6.555,6.555,0,0,1,.368-.8A2.222,2.222,0,0,1,55.279,2.03l.007,52.254a.918.918,0,0,1-.476.8,21.8,21.8,0,0,1-3.318.058,1.737,1.737,0,0,1-.644-.521V49.564H42.8v5.062a1.146,1.146,0,0,1-.3.4,10.977,10.977,0,0,1-3.706,0,1.4,1.4,0,0,1-.421-.511V49.564H4.446v5.062a1.736,1.736,0,0,1-.644.521,21.8,21.8,0,0,1-3.318-.058.918.918,0,0,1-.476-.8L.015,2.03A2.222,2.222,0,0,1,4.078,1a6.551,6.551,0,0,1,.368.8ZM2.814,1.631H1.648v51.89H2.814Zm49.667,51.89h1.166V1.631H52.481ZM14.006,12.1v-1.92c0-.568-1.027-1.446-1.6-1.543a40.969,40.969,0,0,0-6.449.024c-.531.111-1.509.985-1.509,1.519V12.1Zm26,41.425h1.166V10.931H40.006ZM50.849,12.678H42.8v1.164h8.045Zm-46.4,6.865H38.373V13.725H4.446Zm38.358,0h8.045V15.47H42.8Zm-4.43,1.629H4.446v1.164H38.373Zm12.475,0H42.8v1.164h8.045ZM38.373,23.965H4.446V34.088a6.542,6.542,0,0,1,.754-.353c1.188-.392,5.605-.353,7-.233A3.712,3.712,0,0,1,15.639,37.7H38.373Zm4.43,4.073h8.045V23.965H42.8Zm8.045,1.629H42.8V30.83h8.045Zm0,2.793H42.8v4.189h8.045ZM14.006,37.7a2.192,2.192,0,0,0-1.421-2.48,37.03,37.03,0,0,0-6.1-.085A2.11,2.11,0,0,0,4.446,37.7Zm36.842.582H42.8v1.164h8.045ZM38.373,39.325H4.446v5.818H38.373Zm4.43,5.818h8.045V41.07H42.8Zm-4.43,1.629H4.446v1.164H38.373Zm12.475,0H42.8v1.164h8.045Z" transform="translate(0 0)" fill="#72c3dc"/><path id="Tracciato_39" data-name="Tracciato 39" d="M65.185,95.589a25.151,25.151,0,0,1,3.565,0,.819.819,0,0,1-.1,1.6,25.246,25.246,0,0,1-3.467-.017.847.847,0,0,1,0-1.581" transform="translate(-54.594 -80.677)" fill="#72c3dc"/></g></svg>';

  // ======================================================================
  // FUNZIONI HELPER
  // ======================================================================
  
  /**
   * Aggiorna il conteggio totale delle persone
   * Questa funzione calcola il numero totale di persone in base ai valori dei campi
   */
  function updateNumPeople() {
    // Utilizziamo il pattern di controllo dell'esistenza prima di leggere i valori
    // per evitare errori se un campo non esiste
    const numAdults = $('#btr_num_adults').length ? parseInt($('#btr_num_adults').val(), 10) || 0 : 0;
    const numInfants = $('#btr_num_infants').length ? parseInt($('#btr_num_infants').val(), 10) || 0 : 0;
    const numChild_f1 = $('#btr_num_child_f1').length ? parseInt($('#btr_num_child_f1').val(), 10) || 0 : 0;
    const numChild_f2 = $('#btr_num_child_f2').length ? parseInt($('#btr_num_child_f2').val(), 10) || 0 : 0;
    
    // Calcolo del totale
    const numChildren = numInfants + numChild_f1 + numChild_f2;
    let totalPeople = numAdults + numChildren;
    
    // Mostra il pulsante di verifica
    $('#btr-check-people').removeClass('hide');
    
    // Validazione del totale
    if (totalPeople < 1) {
      totalPeople = 0;
    }
    
    // Aggiorna il campo nascosto con il totale
    $('#btr_num_people').val(totalPeople);
  }
  
  /**
   * Restituisce l'icona appropriata per il tipo di camera
   * @param {string} roomType - Il tipo di camera
   * @return {string} - L'HTML dell'icona SVG
   */
  function getRoomIcon(roomType) {
    const rt = roomType.toLowerCase();
    
    // Utilizziamo un approccio più leggibile con if/else invece di concatenazioni di condizioni
    if (rt.includes("singola")) {
      return icona_singola;
    } else if (rt.includes("doppia")) {
      return icona_doppia;
    } else if (rt.includes("tripla")) {
      return icona_tripla;
    } else if (rt.includes("quadrupla")) {
      return icona_quadrupla;
    } else if (rt.includes("quintupla")) {
      return icona_quintupla;
    } else if (rt.includes("condivisa")) {
      return icona_condivisa;
    } else {
      return ''; // Nessuna icona di default
    }
  }
  
  /**
   * Converte una stringa in formato slug
   * @param {string} str - La stringa da convertire
   * @return {string} - Lo slug risultante
   */
  function slugify(str) {
    return str.toString().toLowerCase()
      .replace(/\s+/g, '-')           // Sostituisce spazi con trattini
      .replace(/[^\w-]+/g, '')        // Rimuove caratteri non alfanumerici
      .replace(/--+/g, '-')           // Sostituisce trattini multipli con uno singolo
      .trim();                         // Rimuove spazi iniziali e finali
  }
  
  /**
   * Sanitizza il tipo di camera per creare ID validi
   * @param {string} roomType - Il tipo di camera
   * @return {string} - La stringa sanitizzata
   */
  function sanitizeRoomType(roomType) {
    return roomType.replace(/\s+/g, '-')  // Sostituisce spazi con trattini
      .replace(/[^\w\-]+/g, '')           // Rimuove caratteri non alfanumerici
      .toLowerCase();                      // Converte in minuscolo
  }
  
  // ======================================================================
  // GESTIONE CARICAMENTO CAMERE
  // ======================================================================
  
  /**
   * Carica le tipologie di camera disponibili
   * @param {number} productId - ID del prodotto
   * @param {number} numPeople - Numero totale di persone
   * @param {number} numAdults - Numero di adulti
   * @param {number} numChildren - Numero di bambini
   * @param {number} numInfants - Numero di neonati
   * @param {number} numChild_f1 - Numero di bambini fascia 1
   * @param {number} numChild_f2 - Numero di bambini fascia 2
   */
  function loadRoomTypes(productId, numPeople, numAdults, numChildren, numInfants, numChild_f1, numChild_f2) {
    const packageId = form.find('input[name="btr_package_id"]').val();
    
    // Nascondi il pulsante di verifica e rimuovi lo stato di caricamento
    $('#btr-check-people').removeClass('running').addClass('hide');
    
    // Chiamata AJAX per recuperare le camere disponibili
    $.post(btr_booking_form.ajax_url, {
      action: 'btr_get_rooms',
      nonce: btr_booking_form.nonce,
      product_id: productId,
      package_id: packageId,
      num_people: numPeople,
      num_adults: numAdults,
      num_children: numChild_f1 + numChild_f2,
    }).done(function (response) {
      if (response.success) {
        // Svuota il contenitore delle camere
        roomTypesContainer.empty();
        
        const rooms = response.data.rooms;
        
        // Itera su ogni camera disponibile
        rooms.forEach(function (room, index) {
          const roomType = room.type;
          const variation_id = room.variation_id;
          const capacity = parseInt(room.capacity, 10);
          const maxAvailableRooms = parseInt(room.stock, 10);
          
          // Prezzi
          const regularPrice = parseFloat(room.regular_price);
          const salePrice = room.sale_price ? parseFloat(room.sale_price) : null;
          const scontoPercent = room.sconto ? parseFloat(room.sconto) : null;
          const isOnSale = room.is_on_sale;
          const supplemento = parseFloat(room.supplemento);
          
          // Determina il prezzo base per persona (senza supplemento)
          let basePricePerPerson = regularPrice;
          if (isOnSale && salePrice !== null && salePrice >= 0) {
            basePricePerPerson = salePrice;
          }
          
          // Calcola il prezzo per persona includendo il supplemento
          const pricePerPerson = basePricePerPerson + supplemento;
          
          // Calcola il prezzo totale per camera (prezzo per persona * capacità)
          let totalRoomPrice = 0;
          let tempAdults = numAdults;
          let tempF1 = numChild_f1;
          let tempF2 = numChild_f2;
          
          // Calcolo più preciso del prezzo totale considerando diverse tariffe
          for (let p = 0; p < capacity; p++) {
            if (tempAdults > 0) {
              totalRoomPrice += pricePerPerson;
              tempAdults--;
            } else if (tempF1 > 0) {
              const priceF1 = parseFloat(room.price_child_f1 || 0);
              totalRoomPrice += priceF1;
              tempF1--;
            } else if (tempF2 > 0) {
              const priceF2 = parseFloat(room.price_child_f2 || 0);
              totalRoomPrice += priceF2;
              tempF2--;
            }
          }
          
          // Formatta il prezzo totale con due decimali
          totalRoomPrice = totalRoomPrice.toFixed(2);
          
          // Prepara il testo dello stock
          const stockText = `
            <div class="btr-stock-info">
              <span class="btr-stock-label">Disponibilità:</span>
              <span class="btr-stock-value"><strong>${maxAvailableRooms} camer${maxAvailableRooms !== 1 ? 'e' : 'a'}</strong></span>
            </div>
          `;
          
          // Prepara il testo del prezzo con gestione condizionale per prezzi scontati
          let priceText = '';
          if (isOnSale && salePrice !== null && salePrice >= 0) {
            priceText = `
              <div class="btr-price">
                Prezzo per persona: 
                <span class="btr-regular-price">€${regularPrice.toFixed(2)}</span>
                <span class="btr-sale-price">€${salePrice.toFixed(2)}</span>
              </div>
            `;
          } else {
            priceText = `
              <div class="btr-price">
                Prezzo per persona: <span class="btr-price-value">€${regularPrice.toFixed(2)}</span>
              </div>
            `;
          }
          
          // Aggiungi informazioni sui neonati se presenti
          if (numInfants > 0) {
            priceText += `
              <div class="btr-price">
                Neonati: <span class="btr-price-value">gratuiti</span>
              </div>
            `;
          }
          
          // Aggiungi informazioni sui bambini fascia 1 se presenti
          if (numChild_f1 > 0) {
            const fascia1Discount = parseFloat(room.price_child_f1 || 0);
            if (room.price_child_f1 && room.price_child_f1 > 0 && fascia1Discount > 0) {
              priceText += `
                <div class="btr-discount-info">
                  <span class="btr-discount-label">Bambini 3-12 anni:</span> 
                  <span class="btr-discount-value"><strong>€${parseFloat(room.price_child_f1).toFixed(2)}</strong> 
                  <span class="btr-sale-price">Riduzione del ${response.data.bambini_fascia1_sconto}%</span></span>
                </div>
              `;
            }
          }
          
          // Aggiungi informazioni sui bambini fascia 2 se presenti
          if (numChild_f2 > 0) {
            const fascia2Discount = parseFloat(room.price_child_f2 || 0);
            if (room.price_child_f2 && room.price_child_f2 > 0 && fascia2Discount > 0) {
              priceText += `
                <div class="btr-discount-info">
                  <span class="btr-discount-label">Bambini 12-14 anni:</span> 
                  <span class="btr-discount-value"><strong>€${parseFloat(room.price_child_f2).toFixed(2)}</strong> 
                  <span class="btr-sale-price">Riduzione del ${response.data.bambini_fascia2_sconto}%</span></span>
                </div>
              `;
            }
          }
          
          // Prepara il testo del supplemento se presente
          let supplementoText = '';
          let inclusoSupplemento = '';
          if (supplemento > 0) {
            supplementoText = `
              <div class="btr-supplemento">
                <span class="btr-supplemento-label">Supplemento:</span>
                <span class="btr-supplemento-value">€${supplemento.toFixed(2)}</span>
              </div>
            `;
            inclusoSupplemento = ` <small>(incluso supplemento)</small>`;
          }
          
          // Testo del prezzo totale per persona (incluso supplemento)
          const totalPricePerPersonText = `
            <div class="btr-total-price-per-person">
              Prezzo persona${inclusoSupplemento}:
              <span class="btr-total-price-value">€${pricePerPerson.toFixed(2)}</span>
            </div>
          `;
          
          // Visualizza lo sconto percentuale se presente
          const sconto_Percentuale = scontoPercent && scontoPercent > 0
            ? `<span class="btr-sale-percent">Promo <strong>${scontoPercent}% Sconto</strong></span>`
            : '';
          
          // Ottieni l'icona appropriata per il tipo di camera
          const roomIcon = getRoomIcon(roomType);
          
          // Genera l'HTML per ogni tipologia di camera
          const roomHtml = `
            <div class="btr-room-card ${maxAvailableRooms === 0 ? 'btr-disabled' : ''}">
              <div class="btr-room-header">
                <div class="btr-room-icon">${roomIcon}</div>
                <h3 class="btr-room-type">Camera <strong>${roomType}</strong></h3> ${sconto_Percentuale}
                <p class="btr-room-capacity">Capacità: <strong>${capacity} person${capacity > 1 ? 'e' : 'a'}</strong></p>
                ${stockText}
              </div>
              <div class="btr-room-body">
                ${priceText}
                ${supplementoText}
                <!--${totalPricePerPersonText}-->
                <div class="btr-total-room-price">
                  Prezzo totale:
                  <span class="btr-total-room-price-value">€${totalRoomPrice}</span>
                </div>
              </div>
              <div class="btr-room-footer">
                <label for="btr-room-quantity-${sanitizeRoomType(roomType)}" class="btr-room-quantity-label">
                  INDICA IL NUMERO DI CAMERE CHE DESIDERI
                </label>
                
                <div class="btr-number-input">
                  <button class="btr-minus" ${maxAvailableRooms === 0 ? 'disabled' : ''}><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#000000" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line></svg></button>
                  <input
                    type="number"
                    id="btr-room-quantity-${sanitizeRoomType(roomType)}"
                    class="btr-room-quantity"
                    data-variation-id="${variation_id}"
                    data-room-type="${roomType}"
                    data-capacity="${capacity}"
                    data-price-per-person="${pricePerPerson}"
                    data-sconto="${scontoPercent}"
                    data-supplemento="${supplemento}"
                    data-stock="${maxAvailableRooms}"
                    min="0"
                    max="${maxAvailableRooms}"
                    value="0"
                    ${maxAvailableRooms === 0 ? 'disabled' : ''}>
                  <button class="btr-plus" ${maxAvailableRooms === 0 ? 'disabled' : ''}><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#000000" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg></button>
                </div>
              </div>
            </div>
          `;
          
          // Aggiungi la card della camera al contenitore
          roomTypesContainer.append(roomHtml);
        });
        
        // Mostra il bottone di Procedi
        proceedButton.slideDown();
        
        // ======================================================================
        // GESTIONE EVENTI PER SELEZIONE CAMERE
        // ======================================================================
        
        /**
         * Aggiorna il riepilogo delle camere selezionate
         * Calcola capacità totale, prezzo e aggiorna l'interfaccia
         */
        function updateSelectedRooms() {
          selectedCapacity = 0;
          totalPrice = 0;
          let roomSummary = {};
          
          // Calcola la capacità selezionata, il prezzo totale e raggruppa le quantità per tipologia
          $('.btr-room-quantity').each(function () {
            const quantity = parseInt($(this).val(), 10) || 0;
            const capacity = parseInt($(this).data('capacity'), 10);
            const roomType = $(this).data('room-type');
            const pricePerPerson = parseFloat($(this).data('price-per-person'));
            
            selectedCapacity += capacity * quantity;
            const totalPersonsInRoom = capacity * quantity;
            
            // Calcolo più preciso del prezzo considerando diverse tariffe
            let adultsLeft = numAdults;
            let infantsLeft = parseInt($('#btr_num_infants').val(), 10) || 0;
            let childF1Left = parseInt($('#btr_num_child_f1').val(), 10) || 0;
            let childF2Left = parseInt($('#btr_num_child_f2').val(), 10) || 0;
            let localTotalPrice = 0;
            
            for (let i = 0; i < totalPersonsInRoom; i++) {
              if (adultsLeft > 0) {
                localTotalPrice += pricePerPerson;
                adultsLeft--;
              } else if (childF1Left > 0) {
                const sconto1 = parseFloat(btr_booking_form.price_child_f1 || 0);
                const prezzoScontato = pricePerPerson - (pricePerPerson * sconto1 / 100);
                localTotalPrice += prezzoScontato;
                childF1Left--;
              } else if (childF2Left > 0) {
                const sconto2 = parseFloat(btr_booking_form.price_child_f2 || 0);
                const prezzoScontato = pricePerPerson - (pricePerPerson * sconto2 / 100);
                localTotalPrice += prezzoScontato;
                childF2Left--;
              } else if (infantsLeft > 0) {
                // Infanti non pagano e non contano come posti occupati
                infantsLeft--;
              }
            }
            
            totalPrice += localTotalPrice;
            
            // Aggiorna il riepilogo delle camere
            if (quantity > 0) {
              if (!roomSummary[roomType]) {
                roomSummary[roomType] = 0;
              }
              roomSummary[roomType] += quantity;
            }
          });
          
          // Crea il testo di riepilogo per le camere selezionate
          let summaryParts = [];
          for (let type in roomSummary) {
            // Aggiunge la "e" finale per plurale se necessario
            let plural = roomSummary[type] > 1 ? 'e' : '';
            summaryParts.push(`${roomSummary[type]} camera${plural} ${type}`);
          }
          let summaryText = summaryParts.join(' - ');
          
          // Aggiorna il display della capacità totale e del report di camere selezionate
          totalCapacityDisplay.html(`Totali posti selezionati: <strong>${selectedCapacity}/${requiredCapacity} | ${summaryText}</strong>`);
          totalPriceDisplay.html(`Prezzo Totale: <strong>€${totalPrice.toFixed(2)}</strong>`);
          
          // Calcola la capacità rimanente
          let remainingCapacity = requiredCapacity - selectedCapacity;
          
          // Aggiorna gli input delle camere e i pulsanti +/-
          $('.btr-room-quantity').each(function () {
            const capacity = parseInt($(this).data('capacity'), 10);
            const stock = parseInt($(this).attr('data-stock'), 10);
            const currentQuantity = parseInt($(this).val(), 10) || 0;
            
            // Calcola la quantità massima consentita in base allo stock e alla capacità rimanente
            let maxAllowedQuantity = Math.min(
              stock,
              currentQuantity + Math.floor(remainingCapacity / capacity)
            );
            
            if (maxAllowedQuantity < 0) {
              maxAllowedQuantity = 0;
            }
            
            $(this).attr('max', maxAllowedQuantity);
            
            const minusButton = $(this).siblings('.btr-minus');
            const plusButton = $(this).siblings('.btr-plus');
            
            // Abilita/disabilita i pulsanti in base ai limiti
            minusButton.prop('disabled', currentQuantity <= 0);
            plusButton.prop('disabled', (currentQuantity >= maxAllowedQuantity) || (remainingCapacity <= 0));
            
            // Disabilita l'intera card se non ci sono camere disponibili
            if (maxAllowedQuantity === 0 && currentQuantity === 0) {
              $(this).prop('disabled', true);
              $(this).closest('.btr-room-card').addClass('btr-disabled');
            } else {
              $(this).prop('disabled', false);
              $(this).closest('.btr-room-card').removeClass('btr-disabled');
            }
          });
          
          // Mostra un avviso se la capacità totale selezionata è inferiore a quella richiesta
          if (selectedCapacity < requiredCapacity) {
            bookingResponse.html('<p class="btr-warning">Attenzione: non tutte le persone hanno una sistemazione. Per favore, modifica la selezione delle camere.</p>');
            proceedButton.slideUp();
            customerSection.slideUp();
          } else {
            bookingResponse.empty();
            proceedButton.slideDown();
          }
        }
        
        // Evento per il cambio di quantità delle camere
        $('.btr-room-quantity').on('input', function () {
          updateSelectedRooms();
        });
        
        // Eventi per i pulsanti + e -
        $('.btr-minus').on('click', function (e) {
          e.preventDefault();
          const input = $(this).siblings('.btr-room-quantity');
          let value = parseInt(input.val(), 10) || 0;
          if (value > parseInt(input.attr('min'), 10)) {
            value--;
            input.val(value);
            input.trigger('input');
          }
        });
        
        $('.btr-plus').on('click', function (e) {
          e.preventDefault();
          const input = $(this).siblings('.btr-room-quantity');
          let value = parseInt(input.val(), 10) || 0;
          const max = parseInt(input.attr('max'), 10);
          if (value < max) {
            value++;
            input.val(value);
            input.trigger('input');
          }
        });
        
        // Inizializza le camere selezionate
        updateSelectedRooms();
      } else {
        // Gestione degli errori
        bookingResponse.html('<p class="btr-error">Errore nel recupero delle camere disponibili. Per favore, riprova più tardi.</p>');
      }
    }).fail(function (jqXHR, textStatus, errorThrown) {
      // Gestione degli errori AJAX
      console.error('Errore AJAX:', textStatus, errorThrown);
      bookingResponse.html('<p class="btr-error">Si è verificato un errore durante la comunicazione con il server. Per favore, riprova più tardi.</p>');
    });
  }
  
  // ======================================================================
  // EVENTI
  // ======================================================================
  
  /**
   * Eventi per aggiornare il totale in tempo reale
   * Utilizziamo un selettore combinato per migliorare le performance
   */
  $('#btr_num_adults, #btr_num_children, #btr_num_infants, #btr_num_child_f1, #btr_num_child_f2').on('input change', function () {
    updateNumPeople();
  });
  
  // Inizializza il valore al caricamento della pagina
  updateNumPeople();
  
  /**
   * Gestione del cambio data
   * Aggiorna l'ID variante e attiva il secondo step
   */
  $('#btr_date').on('change', function () {
    const selectedOption = $(this).find(':selected'); // Trova l'opzione selezionata
    const selectedDate = selectedOption.val(); // Ottieni il valore della data
    const variantId = selectedOption.data('id'); // Ottieni l'ID variante dal data-id
    const title = $(this).data('title');
    const desc = $(this).data('desc');
    
    if (variantId) {
      $('#btr_selected_variant_id').val(variantId); // Imposta l'ID variante nell'input nascosto
      $('#title-step').text(title);
      $('#desc-step').text(desc);
      $('.timeline .step.step2').addClass('active');
      console.log(`Variant ID for selected date (${selectedDate}): ${variantId}`);
    } else {
      alert('Errore: Nessuna variante trovata per la data selezionata.');
    }
  });
  
  // ======================================================================
  // STEP 1: SELEZIONE DELLA DATA
  // ======================================================================
  
  /**
   * Quando l'utente seleziona una data, imposta il valore di btr_date_ranges_id
   * e mostra la sezione per il numero di persone
   */
  form.on('change', '#btr_date', function () {
    const selectedDateRangeId = $(this).val(); // Supponendo che il valore sia l'ID della variazione
    $('#btr_date_ranges_id').val(selectedDateRangeId);
    
    // Mostra la sezione per il numero di persone
    numPeopleSection.slideDown();
    
    // Nasconde le altre sezioni e resetta i valori
    roomTypesSection.slideUp();
    roomTypesContainer.empty();
    proceedButton.slideUp();
    customerSection.slideUp();
    totalCapacityDisplay.html('Capacità Totale Selezionata: 0 / <span id="btr-required-capacity">0</span>');
    requiredCapacityDisplay.text('');
    selectedCapacity = 0;
    totalPrice = 0;
    totalPriceDisplay.text('Prezzo Totale: €0.00');
    bookingResponse.empty();
  });
  
  // ======================================================================
  // STEP 2: VERIFICA DISPONIBILITÀ IN BASE AL NUMERO DI PERSONE
  // ======================================================================
  
  /**
   * Gestisce il click sul pulsante di verifica disponibilità
   * Controlla se ci sono camere disponibili per il numero di persone selezionato
   */
  $('#btr-check-people').on('click', function (e) {
    e.preventDefault();
    
    const productId = form.find('input[name="btr_product_id"]').val();
    const packageId = form.find('input[name="btr_package_id"]').val();
    const numPeople = parseInt($('#btr_num_people').val(), 10);
    const numAdults = parseInt(numAdultsField.val(), 10) || 0;
    const numInfants = parseInt(numInfantsField.val(), 10) || 0;
    const numChild_f1 = parseInt($('#btr_num_child_f1').val(), 10) || 0;
    const numChild_f2 = parseInt($('#btr_num_child_f2').val(), 10) || 0;
    const numChildren = numChild_f1 + numChild_f2;
    const title = $(this).data('title');
    const desc = $(this).data('desc');
    
    $(this).addClass('running');
    
    // Validazione
    if (isNaN(numPeople) || numPeople < 1) {
      alert('Inserisci un numero valido di persone.');
      $(this).removeClass('running');
      return;
    }
    
    // Imposta la capacità richiesta (esclusi gli infanti)
    requiredCapacity = numAdults + numChild_f1 + numChild_f2;
    requiredCapacityDisplay.text(requiredCapacity);
    selectedCapacity = 0; // Resetta il conteggio
    totalPrice = 0;
    
    console.log('Verifica Disponibilità - Product ID:', productId, 'Numero di Persone:', numPeople);
    
    // Chiamata AJAX per verificare la disponibilità
    $.post(btr_booking_form.ajax_url, {
      action: 'btr_check_availability',
      nonce: btr_booking_form.nonce,
      product_id: productId,
      package_id: packageId,
      num_people: numPeople,
    }).done(function (response) {
      if (response.success) {
        console.log('Disponibilità confermata.');
        // Mostra la sezione per la selezione delle camere
        roomTypesSection.slideDown();
        loadRoomTypes(productId, numPeople, numAdults, numChildren, numInfants, numChild_f1, numChild_f2);
        $('#title-step').text(title);
        $('#desc-step').text(desc);
        $('.timeline .step.step3').addClass('active');
      } else {
        console.log('Errore Disponibilità:', response.data.message);
        alert(response.data.message);
        // Nasconde le sezioni e resetta i valori
        roomTypesSection.slideUp();
        roomTypesContainer.empty();
        proceedButton.slideUp();
        customerSection.slideUp();
        totalCapacityDisplay.html('Capacità Totale Selezionata: 0 / <span id="btr-required-capacity">0</span>');
        requiredCapacityDisplay.text('');
        selectedCapacity = 0;
        totalPrice = 0;
        totalPriceDisplay.text('Prezzo Totale: €0.00');
        $(this).removeClass('running');
      }
    }).fail(function (jqXHR, textStatus, errorThrown) {
      console.error('AJAX Error:', textStatus, errorThrown);
      alert('Errore durante la verifica della disponibilità.');
    });
  });
  
  // ======================================================================
  // STEP 3: PROCEDI ALLA SEZIONE DEL CLIENTE
  // ======================================================================
  
  /**
   * Gestisce il click sul pulsante Procedi
   * Mostra la sezione per inserire i dati del cliente
   */
  proceedButton.on('click', function (e) {
    e.preventDefault();
    const title = $(this).data('title');
    const desc = $(this).data('desc');
    
    $('#title-step').text(title);
    $('#desc-step').text(desc);
    
    // Mostra la sezione del cliente
    customerSection.slideDown();
    
    // Nasconde il bottone di Procedi per evitare duplicazioni
    proceedButton.slideUp();
  });
  
  // ======================================================================
  // STEP 4: GESTIONE DEL SUBMIT DEL FORM (CREA PREVENTIVO)
  // ======================================================================
  
  /**
   * Gestisce l'invio del form per la creazione del preventivo
   * Raccoglie tutti i dati e li invia al server
   */
  form.on('submit', function (e) {
    e.preventDefault();
    $('.btn-crea-preventivo-form').addClass('running');
    
    // Raccogli i dati del cliente
    const clienteNome = $('#btr_cliente_nome').val().trim();
    const clienteEmail = $('#btr_cliente_email').val().trim();
    
    // Validazione dei dati del cliente
    if (clienteNome === '' || clienteEmail === '') {
      alert('Per favore, inserisci il nome e l\'email del cliente.');
      $('.btn-crea-preventivo-form').removeClass('running');
      return;
    }
    
    // Raccogli i dati delle camere selezionate
    let rooms = [];
    $('.btr-room-quantity').each(function () {
      const quantity = parseInt($(this).val(), 10);
      if (quantity > 0) {
        const roomType = $(this).data('room-type');
        const capacity = parseInt($(this).data('capacity'), 10);
        const pricePerPerson = parseFloat($(this).data('price-per-person'));
        const variationId = parseInt($(this).data('variation-id'), 10);
        const regularPrice = parseFloat($(this).data('regular-price')) || 0;
        const salePrice = parseFloat($(this).data('sale-price')) || 0;
        const supplemento = parseFloat($(this).data('supplemento')) || 0;
        
        rooms.push({
          variation_id: variationId,
          tipo: roomType,
          quantita: quantity,
          prezzo_per_persona: pricePerPerson,
          sconto: $(this).data('sconto') ? parseFloat($(this).data('sconto')) : 0,
          regular_price: regularPrice,
          sale_price: salePrice,
          supplemento: supplemento,
          capacity: capacity,
        });
      }
    });
    
    // Validazione delle camere selezionate
    if (rooms.length === 0) {
      alert('Seleziona almeno una camera.');
      $('.btn-crea-preventivo-form').removeClass('running');
      return;
    }
    
    // Verifica che la capacità selezionata corrisponda a quella richiesta
    if (selectedCapacity !== requiredCapacity) {
      alert(`La capacità totale delle camere selezionate (${selectedCapacity}) non corrisponde al numero di persone (${requiredCapacity}).`);
      $('.btn-crea-preventivo-form').removeClass('running');
      return;
    }
    
    console.log('Camere Selezionate (prima assegnazione):', rooms);
    console.log(`Capacità Totale Selezionata: ${selectedCapacity} / ${requiredCapacity}`);
    console.log(`Prezzo Totale: €${totalPrice.toFixed(2)}`);
    
    // Raccogli i dati dei partecipanti
    const anagrafici = [];
    $('#btr-assicurazioni-container .btr-person-card').each(function (index) {
      const assicurazioni = {};
      
      // Raccogli le assicurazioni selezionate
      $(this).find('input[type="checkbox"]:checked').each(function () {
        const name = $(this).attr('name');
        const match = name.match(/anagrafici\[\d+\]\[assicurazioni\]\[([^\]]+)\]/);
        if (match && match[1]) {
          assicurazioni[match[1]] = true;
        }
      });
      
      // Crea l'oggetto persona con tutti i dati
      const persona = {
        nome: $(this).find('input[name$="[nome]"]').val() || '',
        cognome: $(this).find('input[name$="[cognome]"]').val() || '',
        data_nascita: $(this).find('input[name$="[data_nascita]"]').val() || '',
        citta_nascita: $(this).find('input[name$="[citta_nascita]"]').val() || '',
        citta_residenza: $(this).find('input[name$="[citta_residenza]"]').val() || '',
        provincia_residenza: $(this).find('input[name$="[provincia_residenza]"]').val() || '',
        email: $(this).find('input[name$="[email]"]').val() || '',
        telefono: $(this).find('input[name$="[telefono]"]').val() || '',
        camera: '',
        camera_tipo: '',
        assicurazioni: assicurazioni,
        assicurazioni_dettagliate: []
      };
      
      anagrafici.push(persona);
    });
    
    // =====================
    // Assegnazione partecipanti per camera (deterministica)
    // =====================
    const adultsTotal   = parseInt($('#btr_num_adults').val(), 10) || 0;
    const infantsTotal  = $('#btr_num_infants').length ? (parseInt($('#btr_num_infants').val(), 10) || 0) : 0;
    const f1Total       = $('#btr_num_child_f1').length ? (parseInt($('#btr_num_child_f1').val(), 10) || 0) : 0;
    const f2Total       = $('#btr_num_child_f2').length ? (parseInt($('#btr_num_child_f2').val(), 10) || 0) : 0;
    const f3Total       = $('#btr_num_child_f3').length ? (parseInt($('#btr_num_child_f3').val(), 10) || 0) : 0;
    const f4Total       = $('#btr_num_child_f4').length ? (parseInt($('#btr_num_child_f4').val(), 10) || 0) : 0;

    let remaining = {
      adults:  adultsTotal,
      infants: infantsTotal,
      f1:      f1Total,
      f2:      f2Total,
      f3:      f3Total,
      f4:      f4Total
    };

    // Espandi per unità per garantire 1 adulto minimo dove richiesto
    const units = [];
    rooms.forEach(r => {
      const q = Math.max(1, parseInt(r.quantita, 10) || 1);
      const cap = Math.max(0, parseInt(r.capacity, 10) || 0);
      for (let i = 0; i < q; i++) {
        units.push({ tipo: r.tipo, capacity: cap, price_per_persona: r.prezzo_per_persona, supplemento: r.supplemento, variation_id: r.variation_id });
      }
    });

    // Assegna: 1 adulto per unità (se possibile)
    units.forEach(u => {
      if (remaining.adults > 0) {
        u.assigned_adults = 1; remaining.adults -= 1; u.cap_left = u.capacity - 1;
      } else {
        u.assigned_adults = 0; u.cap_left = u.capacity;
      }
      u.assigned_child_f1 = 0; u.assigned_child_f2 = 0; u.assigned_child_f3 = 0; u.assigned_child_f4 = 0; u.assigned_infants = 0;
    });

    // Assegna bambini per fascia poi neonati
    ['f1','f2','f3','f4'].forEach(fx => {
      units.forEach(u => {
        if (remaining[fx] > 0 && u.cap_left > 0) {
          const take = Math.min(remaining[fx], u.cap_left);
          u['assigned_child_' + fx] += take; remaining[fx] -= take; u.cap_left -= take;
        }
      });
    });
    // Neonati
    units.forEach(u => {
      if (remaining.infants > 0 && u.cap_left > 0) {
        const take = Math.min(remaining.infants, u.cap_left);
        u.assigned_infants += take; remaining.infants -= take; u.cap_left -= take;
      }
    });

    // Ricompone per tipologia
    const grouped = {};
    units.forEach(u => {
      const key = `${u.variation_id}|${u.tipo}`;
      if (!grouped[key]) {
        grouped[key] = { variation_id: u.variation_id, tipo: u.tipo, quantita: 0, prezzo_per_persona: u.price_per_persona, supplemento: u.supplemento, capacity: u.capacity,
          assigned_adults: 0, assigned_child_f1: 0, assigned_child_f2: 0, assigned_child_f3: 0, assigned_child_f4: 0, assigned_infants: 0 };
      }
      grouped[key].quantita += 1;
      grouped[key].assigned_adults   += u.assigned_adults;
      grouped[key].assigned_child_f1 += u.assigned_child_f1;
      grouped[key].assigned_child_f2 += u.assigned_child_f2;
      grouped[key].assigned_child_f3 += u.assigned_child_f3;
      grouped[key].assigned_child_f4 += u.assigned_child_f4;
      grouped[key].assigned_infants  += u.assigned_infants;
    });
    rooms = Object.values(grouped);

    // Coerenza finale: se rimangono partecipanti non assegnati, blocca submit con messaggio chiaro
    if (remaining.adults > 0 || remaining.f1 > 0 || remaining.f2 > 0 || remaining.f3 > 0 || remaining.f4 > 0 || remaining.infants > 0) {
      $('.btn-crea-preventivo-form').removeClass('running');
      alert('Attenzione: alcuni partecipanti non sono stati assegnati alle camere. Controlla la selezione e riprova.');
      return;
    }

    console.log('Camere con assegnazioni:', rooms);

    // Invia i dati al server per creare il preventivo (FormData)
    const fd = new FormData();
    fd.append('action', 'btr_create_preventivo');
    fd.append('nonce', btr_booking_form.nonce);
    fd.append('cliente_nome', clienteNome);
    fd.append('cliente_email', clienteEmail);
    fd.append('package_id', $('input[name="btr_package_id"]').val());
    fd.append('product_id', $('input[name="btr_product_id"]').val());
    fd.append('variant_id', $('input[name="selected_variant_id"]').val());
    fd.append('date_ranges_id', $('input[name="btr_date_ranges_id"]').val());
    fd.append('tipologia_prenotazione', $('input[name="btr_tipologia_prenotazione"]').val());
    fd.append('durata', $('input[name="btr_durata"]').val());
    fd.append('nome_pacchetto', $('input[name="btr_nome_pacchetto"]').val());
    fd.append('prezzo_totale', totalPrice.toFixed(2));
    fd.append('num_adults', adultsTotal);
    fd.append('num_infants', infantsTotal);
    fd.append('num_children', f1Total + f2Total + f3Total + f4Total);
    fd.append('camere', JSON.stringify(rooms));
    fd.append('anagrafici', JSON.stringify(anagrafici));

    $.ajax({
      url: btr_booking_form.ajax_url,
      method: 'POST',
      data: fd,
      processData: false,
      contentType: false
    }).done(function (response) {
      if (response.success) {
        console.log('Preventivo creato con successo.');
        $('.btn-crea-preventivo-form').removeClass('running');
        bookingResponse.html('<p class="btr-success">Preventivo creato con successo! Verrai reindirizzato al riepilogo del preventivo.</p>');
        
        // Reindirizza alla pagina di riepilogo del preventivo dopo un breve ritardo
        setTimeout(function () {
          window.location.href = response.data.redirect_url;
        }, 2000); // 2 secondi
      } else {
        console.log('Errore Preventivo:', response.data.message);
        bookingResponse.html(`<p class="btr-error">${response.data.message}</p>`);
        $('.btn-crea-preventivo-form').removeClass('running');
      }
    }).fail(function (jqXHR, textStatus, errorThrown) {
      console.error('AJAX Error:', textStatus, errorThrown);
      $('.btn-crea-preventivo-form').removeClass('running');
      alert('Errore durante la creazione del preventivo.');
    });
  });
  
  // ======================================================================
  // GESTIONE ASSICURAZIONI E PARTECIPANTI
  // ======================================================================
  
  /**
   * Gestisce il click sul pulsante per generare il template delle assicurazioni
   */
  $(document).on('click', '#btr-generate-insurance-template', function (e) {
    e.preventDefault();
    
    const preventivoId = $('input[name="preventivo_id"]').val() || 0;
    const packageId = $('input[name="btr_package_id"]').val();
    const numAdults = parseInt($('#btr_num_adults').val(), 10) || 0;
    const numChild_f1 = parseInt($('#btr_num_child_f1').val(), 10) || 0;
    const numChild_f2 = parseInt($('#btr_num_child_f2').val(), 10) || 0;
    const numInfants = parseInt($('#btr_num_infants').val(), 10) || 0;
    const numChildren = numChild_f1 + numChild_f2;
    const numPeople = numAdults + numChildren; // Gli infanti non contano
    
    if (numPeople < 1) {
      alert('Inserisci almeno un partecipante.');
      return;
    }
    
    $.post(btr_booking_form.ajax_url, {
      action: 'btr_get_template_assicurazioni',
      nonce: btr_booking_form.nonce,
      preventivo_id: preventivoId,
      num_people: numPeople,
      package_id: packageId
    }).done(function (response) {
      if (response.success) {
        $('#btr-assicurazioni-response').html(response.data.html);
        $('html, body').animate({
          scrollTop: $('#btr-assicurazioni-response').offset().top
        }, 600);
      } else {
        alert(response.data.message || 'Errore nel caricamento del template.');
      }
    }).fail(function () {
      alert('Errore durante la richiesta al server.');
    });
  });
  
  /**
   * Gestisce il click sul pulsante per generare la lista dei partecipanti con assicurazioni
   */
  $(document).on('click', '#btr-generate-participants', function (e) {
    e.preventDefault();
    console.log('CLICK TRIGGERED: #btr-generate-participants');
    
    const numAdults = parseInt($('#btr_num_adults').val(), 10) || 0;
    const numChild_f1 = parseInt($('#btr_num_child_f1').val(), 10) || 0;
    const numChild_f2 = parseInt($('#btr_num_child_f2').val(), 10) || 0;
    const numInfants = $('#btr_num_infants').length ? parseInt($('#btr_num_infants').val(), 10) || 0 : 0;
    const numPeople = numAdults + numChild_f1 + numChild_f2;
    const hasChildren = numChild_f1 > 0 || numChild_f2 > 0;
    const hasInfants = numInfants > 0;
    
    if (numPeople < 1) {
      alert('Inserisci almeno un partecipante.');
      return;
    }
    
    const preventivoId = $('input[name="preventivo_id"]').val() || 0;
    const packageId = $('input[name="btr_package_id"]').val();
    
    $.post(btr_booking_form.ajax_url, {
      action: 'btr_get_assicurazioni_config',
      nonce: btr_booking_form.nonce,
      preventivo_id: preventivoId,
      package_id: packageId
    }).done(function (response) {
      if (response.success && response.data && response.data.assicurazioni) {
        const assicurazioniDisponibili = response.data.assicurazioni;
        let html = '<div id="btr-assicurazioni-container">';
        const ordinali = ['Primo', 'Secondo', 'Terzo', 'Quarto', 'Quinto', 'Sesto'];
        
        console.log('Assicurazioni disponibili:', assicurazioniDisponibili);
        
        if (hasInfants) {
          html += `<p><strong>Neonati:</strong> ${numInfants} (non pagano e non occupano posti)</p>`;
        }
        
        for (let i = 0; i < numPeople; i++) {
          let posizione = '<strong>'+ordinali[i] + '</strong> partecipante' || `Partecipante ${i + 1}`;
          
          if (hasChildren) {
            if (i < numAdults) {
              posizione += ' <small>(Adulto)</small>';
            } else if (i < numAdults + numChild_f1) {
              posizione += ' <small>(Bambino 3-12)</small>';
            } else {
              posizione += ' <small>(Bambino 12-14)</small>';
            }
          }
          
          html += `
            <div class="btr-person-card" data-person-index="${i}">
              <h3 class="person-title">
                <span class="icona-partecipante">
                  <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#000000" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5.52 19c.64-2.2 1.84-3 3.22-3h6.52c1.38 0 2.58.8 3.22 3"/><circle cx="12" cy="10" r="3"/><circle cx="12" cy="12" r="10"/></svg>
                </span>
                <span>${posizione}</span>
              </h3>
              <div class="btr-grid">
                <div class="btr-field-group">
                  <label for="btr_nome_${i}">Nome</label>
                  <input type="text" id="btr_nome_${i}" name="anagrafici[${i}][nome]" />
                </div>
                <div class="btr-field-group">
                  <label for="btr_cognome_${i}">Cognome</label>
                  <input type="text" id="btr_cognome_${i}" name="anagrafici[${i}][cognome]" />
                </div>
                <div class="btr-field-group">
                  <label for="btr_data_nascita_${i}">Data di Nascita</label>
                  <input type="date" id="btr_data_nascita_${i}" name="anagrafici[${i}][data_nascita]" />
                </div>
                <div class="btr-field-group">
                  <label for="btr_citta_nascita_${i}">Città di Nascita</label>
                  <input type="text" id="btr_citta_nascita_${i}" name="anagrafici[${i}][citta_nascita]" />
                </div>
                <div class="btr-field-group">
                  <label for="btr_citta_residenza_${i}">Città di Residenza</label>
                  <input type="text" id="btr_citta_residenza_${i}" name="anagrafici[${i}][citta_residenza]" />
                </div>
                <div class="btr-field-group">
                  <label for="btr_provincia_residenza_${i}">Provincia di Residenza</label>
                  <input type="text" id="btr_provincia_residenza_${i}" name="anagrafici[${i}][provincia_residenza]" />
                </div>
                <div class="btr-field-group">
                  <label for="btr_email_${i}">Email</label>
                  <input type="email" id="btr_email_${i}" name="anagrafici[${i}][email]" />
                </div>
                <div class="btr-field-group">
                  <label for="btr_telefono_${i}">Telefono</label>
                  <input type="tel" id="btr_telefono_${i}" name="anagrafici[${i}][telefono]" />
                </div>
              </div>
              <fieldset class="btr-assicurazioni">
                <h4>Assicurazioni</h4>
          `;
          
          if (assicurazioniDisponibili.length > 0) {
            assicurazioniDisponibili.forEach(ass => {
              const slug = ass.slug ? ass.slug : slugify(ass.descrizione || 'undefined');
              const descrizione = ass.descrizione || 'Assicurazione';
              const importo = parseFloat(ass.importo || 0).toFixed(2);
              const percentuale = parseFloat(ass.percentuale || 0);
              const viewPrezzo = ass.assicurazione_view_prezzo;
              const viewPercentuale = ass.assicurazione_view_percentuale;
              
              let extraText = '';
              if (viewPrezzo && importo > 0) {
                extraText += ` <strong>${importo} €</strong>`;
              }
              if (viewPercentuale && percentuale > 0) {
                extraText += ` <strong>+ ${percentuale}%</strong>`;
              }
              
              html += `
                <div class="btr-assicurazione-item">
                  <label>
                    <input type="checkbox" name="anagrafici[${i}][assicurazioni][${slug}]" value="1" />
                    ${descrizione}${extraText}
                  </label>
                </div>
              `;
            });
          } else {
            html += '<p>Nessuna assicurazione disponibile.</p>';
          }
          
          html += `</fieldset></div>`;
        }
        
        html += '</div>';
        
        $('#btr-participants-wrapper').html(html);
        $('html, body').animate({
          scrollTop: $('#btr-participants-wrapper').offset().top
        }, 600);
      } else {
        alert(response.data ? response.data.message : 'Nessuna assicurazione disponibile.');
      }
    }).fail(function () {
      alert('Errore durante il recupero delle assicurazioni.');
    });
  });
});

// Stampa un messaggio per indicare che lo script è stato caricato correttamente
console.log('Script di prenotazione BTR caricato correttamente');
