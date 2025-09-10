/**
 * Sistema di validazione età bambini - Frontend
 * 
 * Valida le età dei bambini durante la compilazione del form
 * e prima della finalizzazione della prenotazione
 * 
 * @since 1.0.15
 */

(function($) {
    'use strict';

    window.BTRChildAgeValidator = {
        
        settings: {},
        
        /**
         * Inizializza il validatore
         */
        init: function() {
            this.settings = window.btrAgeValidator?.settings || {};
            
            if (!this.settings.enabled) {
                return; // Validazione disabilitata
            }
            
            // Binding eventi
            this.bindEvents();
            
            console.log('BTR Child Age Validator: Inizializzato', this.settings);
        },

        /**
         * Binding eventi
         */
        bindEvents: function() {
            // Validazione quando cambiano date di nascita
            $(document).on('change', 'input[name$="[data_nascita]"]', this.onBirthDateChange.bind(this));
            
            // Validazione quando cambia data partenza
            $(document).on('change', '#btr_selected_date, .btr-date-picker', this.onDepartureDateChange.bind(this));
            
            // Validazione prima dell'invio del preventivo
            $(document).on('btr_before_submit_preventivo', this.validateBeforeSubmit.bind(this));
            
            // Validazione durante la compilazione anagrafici
            $(document).on('btr_anagrafici_updated', this.validateCurrentState.bind(this));
        },

        /**
         * Handler cambio data di nascita
         */
        onBirthDateChange: function(e) {
            const $input = $(e.target);
            setTimeout(() => {
                this.validateSingleChild($input);
            }, 100);
        },

        /**
         * Handler cambio data partenza
         */
        onDepartureDateChange: function(e) {
            setTimeout(() => {
                this.validateAllChildren();
            }, 100);
        },

        /**
         * Validazione singolo bambino
         */
        validateSingleChild: function($birthInput) {
            const birthDate = $birthInput.val();
            if (!birthDate) return;

            const departureDate = this.getDepartureDate();
            if (!departureDate) return;

            const $container = $birthInput.closest('.anagrafici-person');
            const personIndex = this.getPersonIndex($container);
            
            const ageData = this.calculateAge(birthDate, departureDate);
            if (!ageData) return;

            const validation = this.validateAge(ageData, personIndex);
            this.displayValidationResult($container, validation);
        },

        /**
         * Validazione tutti i bambini
         */
        validateAllChildren: function() {
            $('input[name$="[data_nascita]"]').each((index, input) => {
                this.validateSingleChild($(input));
            });
        },

        /**
         * Validazione stato corrente
         */
        validateCurrentState: function() {
            if (!this.settings.enabled) return;
            
            setTimeout(() => {
                this.validateAllChildren();
            }, 500);
        },

        /**
         * Validazione prima dell'invio
         */
        validateBeforeSubmit: function(e, formData) {
            if (!this.settings.enabled) return true;

            const anagrafici = this.collectAnagraficiData();
            const departureDate = this.getDepartureDate();
            
            if (!departureDate) {
                if (this.settings.strict_validation) {
                    this.showError('Data di partenza richiesta per la validazione età bambini');
                    e.preventDefault();
                    return false;
                }
                return true;
            }

            const validation = this.validateAnagrafici(anagrafici, departureDate);
            
            if (!validation.valid && this.settings.strict_validation) {
                this.showValidationErrors(validation);
                e.preventDefault();
                return false;
            } else if (validation.warnings.length > 0 && this.settings.show_warnings) {
                this.showValidationWarnings(validation);
                
                if (this.settings.strict_validation) {
                    e.preventDefault();
                    return false;
                }
            }
            
            return true;
        },

        /**
         * Calcola età alla data di partenza
         */
        calculateAge: function(birthDate, departureDate) {
            try {
                const birth = new Date(birthDate);
                const departure = new Date(departureDate);
                
                if (birth >= departure) {
                    return null;
                }
                
                let years = departure.getFullYear() - birth.getFullYear();
                let months = departure.getMonth() - birth.getMonth();
                let days = departure.getDate() - birth.getDate();
                
                if (days < 0) {
                    months--;
                    const lastMonth = new Date(departure.getFullYear(), departure.getMonth(), 0);
                    days += lastMonth.getDate();
                }
                
                if (months < 0) {
                    years--;
                    months += 12;
                }
                
                return {
                    years: years,
                    months: months,
                    days: days,
                    totalMonths: (years * 12) + months
                };
            } catch (e) {
                return null;
            }
        },

        /**
         * Valida età contro categorie disponibili
         */
        validateAge: function(ageData, personIndex) {
            const categories = this.getChildCategories();
            let correctCategory = null;
            
            // Trova categoria corretta
            for (const category of categories) {
                if (this.isAgeInCategory(ageData, category)) {
                    correctCategory = category;
                    break;
                }
            }
            
            // Ottieni categoria selezionata (se disponibile)
            const selectedCategory = this.getSelectedCategory(personIndex);
            
            const result = {
                ageData: ageData,
                correctCategory: correctCategory,
                selectedCategory: selectedCategory,
                isValid: true,
                needsCorrection: false,
                message: '',
                type: 'success'
            };
            
            if (!correctCategory) {
                result.isValid = false;
                result.type = 'error';
                result.message = `Età ${ageData.years} anni non rientra in nessuna categoria disponibile`;
                return result;
            }
            
            if (selectedCategory && selectedCategory.id !== correctCategory.id) {
                result.isValid = false;
                result.needsCorrection = true;
                result.type = this.settings.strict_validation ? 'error' : 'warning';
                result.message = `Età ${ageData.years} anni dovrebbe essere in categoria "${correctCategory.label}" invece di "${selectedCategory.label}"`;
            } else {
                result.message = `Età ${ageData.years} anni valida per categoria "${correctCategory.label}"`;
            }
            
            return result;
        },

        /**
         * Verifica se età rientra in categoria
         */
        isAgeInCategory: function(ageData, category) {
            const ageMonths = ageData.totalMonths;
            const minMonths = category.age_min * 12;
            const maxMonths = category.age_max * 12;
            
            let isInRange = ageMonths >= minMonths && ageMonths < maxMonths;
            
            // Applica tolleranza se abilitata
            if (!isInRange && this.settings.allow_age_tolerance) {
                const tolerance = this.settings.tolerance_months || 3;
                isInRange = ageMonths >= (minMonths - tolerance) && ageMonths < (maxMonths + tolerance);
            }
            
            return isInRange;
        },

        /**
         * Ottiene categorie child disponibili
         */
        getChildCategories: function() {
            // Prova a ottenere da sistema dinamico
            if (window.btrChildCategories?.categories) {
                return Object.values(window.btrChildCategories.categories);
            }
            
            // Fallback a categorie predefinite
            return [
                {id: 'f1', age_min: 3, age_max: 8, label: 'Bambini 3-8 anni'},
                {id: 'f2', age_min: 8, age_max: 12, label: 'Bambini 8-12 anni'},
                {id: 'f3', age_min: 12, age_max: 14, label: 'Bambini 12-14 anni'},
                {id: 'f4', age_min: 14, age_max: 15, label: 'Bambini 14-15 anni'}
            ];
        },

        /**
         * Ottiene data di partenza
         */
        getDepartureDate: function() {
            // Prova diversi selettori per la data
            const selectors = [
                '#btr_selected_date',
                'input[name="btr_selected_date"]',
                '.btr-date-picker',
                'input[name="departure_date"]'
            ];
            
            for (const selector of selectors) {
                const $input = $(selector);
                if ($input.length && $input.val()) {
                    return $input.val();
                }
            }
            
            return null;
        },

        /**
         * Ottiene indice persona dal container
         */
        getPersonIndex: function($container) {
            const indexMatch = $container.attr('class')?.match(/person-(\d+)/);
            return indexMatch ? parseInt(indexMatch[1]) : 0;
        },

        /**
         * Ottiene categoria selezionata per una persona
         */
        getSelectedCategory: function(personIndex) {
            // Logica per determinare la categoria selezionata
            // Questo dipende da come è strutturato il form
            const categories = this.getChildCategories();
            
            // Per ora returna null, implementare logica specifica
            return null;
        },

        /**
         * Raccoglie dati anagrafici
         */
        collectAnagraficiData: function() {
            const anagrafici = [];
            
            $('.anagrafici-person, .btr-anagrafici-item').each(function() {
                const $person = $(this);
                const data = {
                    nome: $person.find('input[name$="[nome]"]').val() || '',
                    cognome: $person.find('input[name$="[cognome]"]').val() || '',
                    data_nascita: $person.find('input[name$="[data_nascita]"]').val() || ''
                };
                
                if (data.nome || data.cognome || data.data_nascita) {
                    anagrafici.push(data);
                }
            });
            
            return anagrafici;
        },

        /**
         * Valida tutti gli anagrafici
         */
        validateAnagrafici: function(anagrafici, departureDate) {
            const results = {
                valid: true,
                warnings: [],
                errors: [],
                children: []
            };
            
            anagrafici.forEach((person, index) => {
                if (!person.data_nascita) return;
                
                const ageData = this.calculateAge(person.data_nascita, departureDate);
                if (!ageData) return;
                
                const validation = this.validateAge(ageData, index);
                
                if (validation.type === 'error') {
                    results.errors.push({
                        index: index,
                        person: person,
                        message: validation.message
                    });
                    results.valid = false;
                } else if (validation.type === 'warning') {
                    results.warnings.push({
                        index: index,
                        person: person,
                        message: validation.message
                    });
                }
                
                results.children.push(validation);
            });
            
            return results;
        },

        /**
         * Mostra risultato validazione per singola persona
         */
        displayValidationResult: function($container, validation) {
            // Rimuovi messaggi esistenti
            $container.find('.age-validation-message').remove();
            
            if (!this.settings.show_warnings && validation.type === 'warning') {
                return;
            }
            
            const iconMap = {
                success: '✅',
                warning: '⚠️',
                error: '❌'
            };
            
            const colorMap = {
                success: '#28a745',
                warning: '#ffc107',
                error: '#dc3545'
            };
            
            const $message = $(`
                <div class="age-validation-message" style="
                    margin-top: 5px;
                    padding: 8px;
                    border-radius: 4px;
                    font-size: 12px;
                    background: ${colorMap[validation.type]}22;
                    border: 1px solid ${colorMap[validation.type]}44;
                    color: ${colorMap[validation.type]};
                ">
                    ${iconMap[validation.type]} ${validation.message}
                </div>
            `);
            
            $container.find('input[name$="[data_nascita]"]').after($message);
            
            // Auto-suggerimenti se abilitati
            if (validation.needsCorrection && this.settings.auto_suggest_corrections && validation.correctCategory) {
                this.showCategoryCorrection($container, validation);
            }
        },

        /**
         * Mostra suggerimento correzione categoria
         */
        showCategoryCorrection: function($container, validation) {
            const $suggestion = $(`
                <div class="age-category-suggestion" style="
                    margin-top: 5px;
                    padding: 8px;
                    background: #e1ecf4;
                    border: 1px solid #0073aa;
                    border-radius: 4px;
                    font-size: 12px;
                ">
                    💡 <strong>Suggerimento:</strong> Categoria corretta: "${validation.correctCategory.label}"
                    <button type="button" class="apply-suggestion" data-category="${validation.correctCategory.id}" style="
                        margin-left: 10px;
                        padding: 2px 6px;
                        font-size: 11px;
                        background: #0073aa;
                        color: white;
                        border: none;
                        border-radius: 2px;
                        cursor: pointer;
                    ">Applica</button>
                </div>
            `);
            
            $container.find('.age-validation-message').after($suggestion);
            
            // Handler per applicare suggerimento
            $suggestion.find('.apply-suggestion').on('click', (e) => {
                const categoryId = $(e.target).data('category');
                this.applyCategoryCorrection(categoryId);
                $suggestion.remove();
            });
        },

        /**
         * Applica correzione categoria
         */
        applyCategoryCorrection: function(categoryId) {
            // Implementa logica per cambiare categoria
            // Questo dipende dalla struttura del form
            console.log('Applicando correzione categoria:', categoryId);
        },

        /**
         * Mostra errori di validazione
         */
        showValidationErrors: function(validation) {
            let message = 'Errori di validazione età bambini:\n\n';
            validation.errors.forEach(error => {
                message += `• ${error.person.nome} ${error.person.cognome}: ${error.message}\n`;
            });
            message += '\nCorreggere le incongruenze prima di procedere.';
            
            alert(message);
        },

        /**
         * Mostra avvisi di validazione
         */
        showValidationWarnings: function(validation) {
            let message = 'Avvisi età bambini:\n\n';
            validation.warnings.forEach(warning => {
                message += `• ${warning.person.nome} ${warning.person.cognome}: ${warning.message}\n`;
            });
            
            if (this.settings.strict_validation) {
                message += '\nCorreggere prima di procedere.';
                alert(message);
            } else {
                message += '\nProcedere comunque?';
                return confirm(message);
            }
        },

        /**
         * Mostra errore generico
         */
        showError: function(message) {
            alert('Errore validazione: ' + message);
        }
    };

    // Inizializza quando DOM è pronto
    $(document).ready(function() {
        window.BTRChildAgeValidator.init();
    });

})(jQuery);