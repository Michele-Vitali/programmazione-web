$(document).ready(function () {

    console.log("JQuery collegato e pronto per il Back-Office!");

    // 1. GESTIONE CAMBIO PAGINA DA NAVBAR (Click-driven)
    $('.nav-link').on('click', function (e) {
        e.preventDefault();

        // Rimuovi classe attiva dai link e aggiungi al corrente
        $('.nav-link').removeClass('active');
        $(this).addClass('active');

        // Nascondi tutte le sezioni e mostra quella target
        const targetSection = $(this).data('target');
        $('.page-section').addClass('hidden-element').removeClass('active-section');
        $('#' + targetSection).removeClass('hidden-element').addClass('active-section');
    });

    // 2. TOGGLE DARK/LIGHT MODE
    $('.theme-mode-container').on('click', function () {
        const body = $('body');
        body.toggleClass("light-mode").toggleClass("dark-mode");
    });

    // 3. SELEZIONE TIPO CONTRATTO 
    $('.type-select').on('change', function () {
        const startup_request = $('.startup-request');
        const span_unit = $('.quantity-unit');
        const current_selection = $(this).val();
        const optional_container = $('.optional-container');

        let new_text = "";
        let quantity_unit = "";

        if (current_selection == 'ricarica') {
            new_text = "Vuoi già partire con del credito iniziale?<br>Se sì, seleziona quanto...";
            quantity_unit = "€";
        } else {
            new_text = "Vuoi già partire con dei minuti iniziali?<br>Se sì, seleziona quanti...";
            quantity_unit = "Minuti";
        }

        startup_request.html(new_text);
        span_unit.html(quantity_unit);
        optional_container.fadeIn(200);
    });

    // 4. CLICK SULLA RIGA DELLA TABELLA (Mostra dettagli o compila il pannello)
    $(document).on('click', '.clickable-row', function (e) {
        // Evitiamo che il click sui pulsanti interni attivi questo handler
        if ($(e.target).closest('.btn-action').length) return;

        const nome = $(this).data('nome');
        const cognome = $(this).data('cognome');
        const tel = $(this).data('tel');

        alert("Dettagli Contratto Selezionato:\nCliente: " + nome + " " + cognome + "\nTelefono: " + tel + "\nStato SIM: Attiva");
    });


    //8. RICERCA SIM (Dinamica dal database)
    $('#btn-search-sim').on('click', function () {
        const termine = $('#search-input-sim').val().trim();

        if (termine === '') {
            $('#results-tbody-sim').html('<tr><td colspan="4" class="text-center">Inserisci un codice da cercare</td></tr>');
            return;
        }

        $('#results-tbody-sim').html('<tr><td colspan="4" class="text-center"><i class="fa-solid fa-spinner fa-pulse"></i> Caricamento...</td></tr>');

        $.ajax({
            url: '../php/search_sim.php',
            type: 'GET',
            data: { q: termine },
            dataType: 'html',
            success: function (response) {
                $('#results-tbody-sim').html(response);
            },
            error: function (xhr, status, error) {
                $('#results-tbody-sim').html('<tr><td colspan="4" class="text-center text-danger">Errore nella ricerca</td></tr>');
            }
        });
    });
    //9 RICERCA CONTRATTI (Dinamica dal database)
    $('#btn-search-contratti').on('click', function () {
        const termine = $('#search-input-contratti').val().trim();

        if (termine === '') {
            $('#results-tbody-contratti').html('<tr><td colspan="5" class="text-center">Inserisci un numero o nominativo da cercare</td></tr>');
            return;
        }

        // Mostriamo caricamento
        $('#results-tbody-contratti').html('<tr><td colspan="5" class="text-center"><i class="fa-solid fa-spinner fa-pulse"></i> Caricamento...</td></tr>');

        // Chiamata AJAX
        $.ajax({
            url: '../php/search_contratti.php',
            type: 'GET',
            data: { q: termine },
            dataType: 'html',
            success: function (response) {
                $('#results-tbody-contratti').html(response);
            },
            error: function () {
                $('#results-tbody-contratti').html('<tr><td colspan="5" class="text-center text-danger">Errore nella ricerca</td></tr>');
            }
        });
    });

    // 10a - Quando si esce dal campo telefono, cerca il tipo contratto e mostra il campo giusto
    $('#insert-tel').on('blur', function () {
        const telefono = $(this).val().trim();
        if (telefono === '') return;

        $.ajax({
            url: '../php/search_contratti.php',
            type: 'GET',
            data: { q: telefono },
            dataType: 'json',
            success: function (data) {
                if (data && data.tipo) {
                    $('#insert-tipo').val(data.tipo);
                    if (data.tipo === 'ricarica') {
                        $('#field-minuti').hide();
                        $('#insert-minuti').val('');
                        $('#field-costo').show();
                        $('#insert-tipo-info')
                            .html('<i class="fa-solid fa-circle-info"></i> Contratto <strong>Ricarica</strong> — credito residuo attuale: <strong>' + parseFloat(data.residuo).toFixed(2) + ' €</strong>')
                            .show();
                    } else {
                        $('#field-costo').hide();
                        $('#insert-cost').val('');
                        $('#field-minuti').show();
                        $('#insert-tipo-info')
                            .html('<i class="fa-solid fa-circle-info"></i> Contratto <strong>Consumo</strong> — minuti residui attuali: <strong>' + data.residuo + ' min</strong>')
                            .show();
                    }
                } else {
                    $('#field-costo').hide();
                    $('#field-minuti').hide();
                    $('#insert-tipo').val('');
                    $('#insert-tipo-info').hide();
                }
            },
            error: function () {
                $('#insert-tipo-info').html('<div class="error-message">⚠️ Impossibile verificare il contratto</div>').show();
            }
        });
    });

    // 10b INSERIMENTO TELEFONATA (AJAX)
    $('#form-insert-call').on('submit', function (e) {
        e.preventDefault();

        const tipo = $('#insert-tipo').val();
        const telefono = $('#insert-tel').val().trim();
        const data_val = $('#insert-date').val();
        const ora_val  = $('#insert-time').val();
        const durata   = $('#insert-duration').val();

        // Validazione lato client
        if (telefono === '') {
            $('#insert-response').html('<div class="error-message">⚠️ Inserisci un numero di telefono</div>');
            return;
        }
        if (tipo === '') {
            $('#insert-response').html('<div class="error-message">⚠️ Numero non riconosciuto — verifica che il contratto esista</div>');
            return;
        }
        if (data_val === '') {
            $('#insert-response').html('<div class="error-message">⚠️ Seleziona una data</div>');
            return;
        }
        if (ora_val === '') {
            $('#insert-response').html('<div class="error-message">⚠️ Seleziona un\'ora</div>');
            return;
        }
        if (!durata || parseInt(durata) <= 0) {
            $('#insert-response').html('<div class="error-message">⚠️ La durata deve essere maggiore di zero</div>');
            return;
        }
        if (tipo === 'ricarica' && ($('#insert-cost').val() === '' || parseFloat($('#insert-cost').val()) <= 0)) {
            $('#insert-response').html('<div class="error-message">⚠️ Inserisci il costo in euro per questo contratto ricarica</div>');
            return;
        }
        if (tipo === 'consumo' && ($('#insert-minuti').val() === '' || parseInt($('#insert-minuti').val()) <= 0)) {
            $('#insert-response').html('<div class="error-message">⚠️ Inserisci i minuti da scalare per questo contratto consumo</div>');
            return;
        }

        const formData = {
            telefono:       telefono,
            data:           data_val,
            ora:            ora_val,
            durata:         durata,
            tipo_contratto: tipo,
            costo:          tipo === 'ricarica' ? $('#insert-cost').val() : 0,
            minuti_scalati: tipo === 'consumo'  ? $('#insert-minuti').val() : 0
        };

        $('#insert-response').html('<div class="info-message"><i class="fa-solid fa-spinner fa-pulse"></i> Registrazione in corso...</div>');

        $.ajax({
            url: '../php/insert_call.php',
            type: 'POST',
            data: formData,
            dataType: 'html',
            success: function (response) {
                $('#insert-response').html(response);
                if (response.includes('successo')) {
                    $('#form-insert-call')[0].reset();
                    $('#field-costo').hide();
                    $('#field-minuti').hide();
                    $('#insert-tipo-info').hide();
                    $('#insert-tipo').val('');
                }
            },
            error: function () {
                $('#insert-response').html('<div class="error-message">❌ Errore di connessione al server</div>');
            }
        });
    });
    // 11 RICERCA TELEFONATE (Dinamica dal database)
    $('#btn-search-telefonate').on('click', function () {
        const termine = $('#search-input').val().trim();

        if (termine === '') {
            $('#results-tbody-telefonate').html('<tr><td colspan="6" class="text-center">Inserisci un nome, cognome o numero di telefono</td></tr>');
            return;
        }

        // Mostriamo caricamento
        $('#results-tbody-telefonate').html('<tr><td colspan="6" class="text-center"><i class="fa-solid fa-spinner fa-pulse"></i> Caricamento...</td></tr>');

        // Chiamata AJAX
        $.ajax({
            url: '../php/search_telefonate.php',
            type: 'GET',
            data: { q: termine },
            dataType: 'html',
            success: function (response) {
                $('#results-tbody-telefonate').html(response);
            },
            error: function () {
                $('#results-tbody-telefonate').html('<tr><td colspan="6" class="text-center text-danger">Errore nella ricerca</td></tr>');
            }
        });
    });
    //12 DELEGA EVENTI: Pulsante ELIMINA (cestino) - apre il modale
    $(document).on('click', '.btn-delete', function (e) {
        e.stopPropagation();

        const row = $(this).closest('.clickable-row');

        const id = row.data('id-chiamata');
        const nome = row.data('nome');
        const cognome = row.data('cognome');
        const tel = row.data('tel');
        const costo = row.data('costo');

        $('#delete-id').val(id);
        $('#del-user').text(nome + " " + cognome);
        $('#del-tel').text(tel);
        $('#del-cost').text(costo + " €");

        $('#delete-modal').removeClass('hidden-element');
    });

    //13 DELEGA EVENTI: Pulsante MODIFICA (matita) - riempie il pannello
    $(document).on('click', '.btn-edit', function (e) {
        e.stopPropagation();

        const row = $(this).closest('.clickable-row');

        const id = row.data('id-chiamata');
        const nome = row.data('nome');
        const cognome = row.data('cognome');
        const tel = row.data('tel');
        const data = row.data('data');
        const ora = row.data('ora');
        const durata = row.data('durata');
        const costo = row.data('costo');

        $('#edit-id').val(id);
        $('#edit-user-display').text(nome + " " + cognome + " (" + tel + ")");
        $('#edit-date').val(data);
        $('#edit-time').val(ora);
        $('#edit-duration').val(durata);
        $('#edit-cost').val(costo);

        $('#edit-panel').removeClass('hidden-element').hide().fadeIn(300);

        $('html, body').animate({
            scrollTop: $("#edit-panel").offset().top
        }, 500);
    });

    // Dopo il punto 13, aggiungi:

    // Annulla Modifica (rimane uguale)
    $('#btn-cancel-edit').on('click', function () {
        $('#edit-panel').fadeOut(200, function () {
            $(this).addClass('hidden-element');
        });
    });

    // Chiudi modale eliminazione (con delega)
    $(document).on('click', '#btn-cancel-del, .modal-overlay', function (e) {
        if (e.target !== this && this.id !== 'btn-cancel-del') return;
        $('#delete-modal').addClass('hidden-element');
    });

    // Prepara il form di modifica per usare AJAX
    $('#edit-panel form').attr('action', '#');

    // INVIO FORM MODIFICA via AJAX
    $('#edit-panel form').on('submit', function (e) {
        e.preventDefault();

        const formData = {
            id_chiamata: $('#edit-id').val(),
            data: $('#edit-date').val(),
            ora: $('#edit-time').val(),
            durata: $('#edit-duration').val(),
            costo: $('#edit-cost').val()
        };

        $.ajax({
            url: '../php/update_call.php',
            type: 'POST',
            data: formData,
            dataType: 'html',
            success: function (response) {
                alert("Modifica completata!");

                $('#edit-panel').fadeOut(200, function () {
                    $(this).addClass('hidden-element');
                });

                const termine = $('#search-input').val().trim();
                if (termine !== '') {
                    $('#btn-search-telefonate').click();
                }
            },
            error: function () {
                alert("Errore durante la modifica");
            }
        });
    });

    // 14 INVIO FORM ELIMINAZIONE (dal modale) via AJAX
    $('#delete-modal form').on('submit', function (e) {
        e.preventDefault();

        const idChiamata = $('#delete-id').val();

        $.ajax({
            url: '../php/delete_call.php',
            type: 'POST',
            data: { id_chiamata: idChiamata },
            dataType: 'html',
            success: function (response) {
                // Chiudo il modale
                $('#delete-modal').addClass('hidden-element');

                // Mostro il risultato (puoi usare un alert o un div apposito)
                alert("Eliminazione completata!");

                // Ricarico la tabella rifacendo la ricerca
                const termine = $('#search-input').val().trim();
                if (termine !== '') {
                    $('#btn-search-telefonate').click();
                }
            },
            error: function () {
                alert("Errore durante l'eliminazione");
            }
        });
    });


    //QUesto serve per fare in modo che puo premere invio per cercare
    $('#search-input').on('keypress', function (e) {
        if (e.which === 13) {
            $('#btn-search-telefonate').click();
        }
    });

    $('#search-input-sim').on('keypress', function (e) {
        if (e.which === 13) {
            $('#btn-search-sim').click();
        }
    });

    $('#search-input-contratti').on('keypress', function (e) {
        if (e.which === 13) {
            $('#btn-search-contratti').click();
        }
    });


});