$(document).ready(function () {

    const TARIFFA_AL_MINUTO = 0.28; // €/minuto per contratti ricarica
    let lastQuery = { q: '', from: '', to: '' };
    let ctxTel = '';

    // =============================================
    // UTILITY
    // =============================================
    function normalizzaNumero(n) {
        return n.replace(/[\s\+]/g, '');
    }

    window.copyText = function (testo, event) {
        if (event) event.stopPropagation();
        navigator.clipboard.writeText(testo).then(function () {
            showToast('✅ Copiato: ' + testo);
        });
    };

    function showToast(msg, duration) {
        duration = duration || 2500;
        let t = $('#residuo-panel');
        $('#residuo-text').html(msg);
        t.removeClass('hidden-element').addClass('visible');
        clearTimeout(window._toastTimer);
        window._toastTimer = setTimeout(function () {
            t.removeClass('visible').addClass('hidden-element');
        }, duration);
    }

    $('#btn-close-residuo').on('click', function () {
        $('#residuo-panel').removeClass('visible').addClass('hidden-element');
    });

    function mostraResiduo(tel) {
        $.get('../php/get_residuo.php', { tel: tel }, function (data) {
            if (data.ok) {
                let fmt = (data.tipo === 'ricarica')
                    ? parseFloat(data.residuo).toFixed(2).replace('.', ',') + ' €'
                    : data.residuo + ' min';
                showToast('<i class="fa-solid fa-wallet"></i> <strong>' + (data.nominativo || tel) + '</strong> — Residuo: <strong>' + fmt + '</strong>', 4000);
            } else {
                showToast('⚠️ Contratto non trovato per ' + tel, 3000);
            }
        }, 'json');
    }

    function navigaSezione(sezioneId) {
        $('.nav-link').removeClass('active');
        $('[data-target="' + sezioneId + '"]').addClass('active');
        $('.page-section').addClass('hidden-element').removeClass('active-section');
        $('#' + sezioneId).removeClass('hidden-element').addClass('active-section');
    }

    // =============================================
    // 1. CAMBIO PAGINA NAVBAR
    // =============================================
    $('.nav-link').on('click', function (e) {
        e.preventDefault();
        $('.nav-link').removeClass('active');
        $(this).addClass('active');
        const target = $(this).data('target');
        $('.page-section').addClass('hidden-element').removeClass('active-section');
        $('#' + target).removeClass('hidden-element').addClass('active-section');
    });

    // =============================================
    // 2. DARK/LIGHT MODE
    // =============================================
    $('.theme-mode-container').on('click', function () {
        $('body').toggleClass('light-mode').toggleClass('dark-mode');
    });

    // =============================================
    // 3. TIPO CONTRATTO (form crea contratto)
    // =============================================
    $('.type-select').on('change', function () {
        const sel = $(this).val();
        $('.startup-request').html(sel === 'ricarica'
            ? 'Vuoi già partire con del credito iniziale?<br>Se sì, seleziona quanto...'
            : 'Vuoi già partire con dei minuti iniziali?<br>Se sì, seleziona quanti...');
        $('.quantity-unit').html(sel === 'ricarica' ? '€' : 'Minuti');
        $('.optional-container').fadeIn(200);
    });

    // =============================================
    // 4. CONTEXT MENU SU RIGHE CON NUMERO
    // =============================================
    $(document).on('contextmenu', '.clickable-row[data-tel]', function (e) {
        e.preventDefault();
        ctxTel = $(this).data('tel') || '';
        if (!ctxTel) return;
        $('#context-menu')
            .removeClass('hidden-element')
            .css({ top: e.pageY, left: e.pageX });
    });

    $(document).on('click', function (e) {
        if (!$(e.target).closest('#context-menu').length) {
            $('#context-menu').addClass('hidden-element');
        }
    });

    $('#ctx-add-call').on('click', function () {
        $('#context-menu').addClass('hidden-element');
        navigaSezione('section-crea-chiamata');
        $('#insert-tel').val(ctxTel).trigger('blur');
    });

    $('#ctx-show-residuo').on('click', function () {
        $('#context-menu').addClass('hidden-element');
        mostraResiduo(ctxTel);
    });

    $('#ctx-copy-tel').on('click', function () {
        $('#context-menu').addClass('hidden-element');
        copyText(ctxTel);
    });

    // =============================================
    // 5. CLICK SU LINK TELEFONO (→ gestione telefonate)
    // =============================================
    $(document).on('click', '.link-tel', function (e) {
        e.stopPropagation();
        const tel = $(this).closest('tr').data('tel') || $(this).data('copy') || $(this).text();
        navigaSezione('section-gestione');
        $('#search-input').val(tel);
        eseguiRicercaTelefonate();
    });

    // =============================================
    // 6. CLICK SU LINK NOMINATIVO (→ cerca contratti)
    // =============================================
    $(document).on('click', '.link-nominativo', function (e) {
        e.stopPropagation();
        const nom = $(this).text().trim();
        navigaSezione('section-contratti');
        $('#search-input-contratti').val(nom);
        eseguiRicercaContratti();
    });

    // =============================================
    // 7. PULSANTE AGGIUNGI TELEFONATA veloce
    // =============================================
    $(document).on('click', '.btn-add-call-quick', function (e) {
        e.stopPropagation();
        const tel = $(this).data('tel');
        navigaSezione('section-crea-chiamata');
        $('#insert-tel').val(tel).trigger('blur');
    });

    // =============================================
    // 8. PULSANTE REFRESH RESIDUO nelle tabelle
    // =============================================
    $(document).on('click', '.btn-refresh-residuo', function (e) {
        e.stopPropagation();
        mostraResiduo($(this).data('tel'));
    });

    // =============================================
    // AUTOCOMPLETE — helper generico
    // =============================================
    function setupAutocomplete(inputId, tipo) {
        $('#' + inputId).autocomplete({
            source: function (req, resp) {
                $.get('../php/search_autocomplete.php', { q: req.term, tipo: tipo }, function (data) {
                    resp(data);
                }, 'json');
            },
            minLength: 1,
            delay: 200,
            select: function (event, ui) {
                $('#' + inputId).val(ui.item.value);
                return false;
            }
        }).autocomplete('instance')._renderItem = function (ul, item) {
            return $('<li>').append('<div>' + item.label + '</div>').appendTo(ul);
        };
    }

    setupAutocomplete('search-input', 'telefonate');
    setupAutocomplete('search-input-contratti', 'contratti');
    setupAutocomplete('search-input-sim', 'sim');

    // =============================================
    // 9. AUTOCOMPLETE INSERT: cerca numero o nominativo,
    //    selezionando popola il campo con il numero
    // =============================================
    $('#insert-tel').autocomplete({
        source: function (req, resp) {
            $.get('../php/search_autocomplete.php', { q: req.term, tipo: 'telefonate' }, function (data) {
                resp(data);
            }, 'json');
        },
        minLength: 1,
        delay: 200,
        select: function (event, ui) {
            $('#insert-tel').val(ui.item.value);
            $('#insert-tel').trigger('blur');
            return false;
        }
    }).autocomplete('instance')._renderItem = function (ul, item) {
        return $('<li>').append('<div>' + item.label + '</div>').appendTo(ul);
    };

    // Lookup tipo contratto quando si esce dal campo telefono
    $('#insert-tel').on('blur', function () {
        const tel = $(this).val().trim();
        if (!tel) return;
        $.ajax({
            url: '../php/search_contratti.php',
            type: 'GET',
            data: { q: normalizzaNumero(tel), mode: 'json' },
            dataType: 'json',
            success: function (data) {
                if (data && data.tipo) {
                    $('#insert-tipo').val(data.tipo);
                    if (data.tipo === 'ricarica') {
                        $('#field-minuti').hide();
                        $('#insert-minuti').val('');
                        $('#field-costo').show();
                        aggiornaCostoCalcolato();
                        $('#insert-tipo-info')
                            .html('<i class="fa-solid fa-circle-info"></i> Contratto <strong>Ricarica</strong> — credito residuo: <strong>' + parseFloat(data.residuo).toFixed(2).replace('.', ',') + ' €</strong>')
                            .show();
                    } else {
                        $('#field-costo').hide();
                        $('#insert-cost').val('');
                        $('#field-minuti').show();
                        $('#insert-tipo-info')
                            .html('<i class="fa-solid fa-circle-info"></i> Contratto <strong>Consumo</strong> — minuti residui: <strong>' + data.residuo + ' min</strong>')
                            .show();
                    }
                } else {
                    $('#field-costo, #field-minuti').hide();
                    $('#insert-tipo').val('');
                    $('#insert-tipo-info').hide();
                }
            }
        });
    });

    // Calcolo automatico costo per ricarica quando cambia durata
    function aggiornaCostoCalcolato() {
        if ($('#insert-tipo').val() === 'ricarica') {
            const min = parseInt($('#insert-duration').val()) || 0;
            $('#insert-cost').val((min * TARIFFA_AL_MINUTO).toFixed(2));
        }
    }
    $('#insert-duration').on('input', aggiornaCostoCalcolato);

    // Per consumo, i minuti da scalare seguono automaticamente la durata
    $('#insert-duration').on('input', function () {
        if ($('#insert-tipo').val() === 'consumo') {
            $('#insert-minuti').val($(this).val());
        }
    });

    // =============================================
    // 10. INSERIMENTO TELEFONATA
    // =============================================
    $('#form-insert-call').on('submit', function (e) {
        e.preventDefault();
        const tipo     = $('#insert-tipo').val();
        const telefono = normalizzaNumero($('#insert-tel').val().trim());
        const data_v   = $('#insert-date').val();
        const ora_v    = $('#insert-time').val();
        const durata   = $('#insert-duration').val();

        if (!telefono) { $('#insert-response').html('<div class="error-message">⚠️ Inserisci un numero di telefono</div>'); return; }
        if (!tipo)     { $('#insert-response').html('<div class="error-message">⚠️ Numero non riconosciuto — verifica che il contratto esista</div>'); return; }
        if (!data_v)   { $('#insert-response').html('<div class="error-message">⚠️ Seleziona una data</div>'); return; }
        if (!ora_v)    { $('#insert-response').html('<div class="error-message">⚠️ Seleziona un\'ora</div>'); return; }
        if (durata === '' || durata === null) { $('#insert-response').html('<div class="error-message">⚠️ Inserisci una durata (può essere negativa per correzioni)</div>'); return; }
        // Nota: costo/minuti negativi sono ammessi per correzioni amministrative

        const formData = {
            telefono:       telefono,
            data:           data_v,
            ora:            ora_v,
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
                    $('#field-costo, #field-minuti').hide();
                    $('#insert-tipo-info').hide();
                    $('#insert-tipo').val('');
                    // Mostra residuo aggiornato
                    mostraResiduo(telefono);
                }
            },
            error: function () {
                $('#insert-response').html('<div class="error-message">❌ Errore di connessione al server</div>');
            }
        });
    });

    // =============================================
    // 11. RICERCA TELEFONATE
    // =============================================
    function eseguiRicercaTelefonate() {
        const q    = $('#search-input').val().trim();
        const from = $('#search-date-from').val();
        const to   = $('#search-date-to').val();

        if (!q && !from && !to) {
            $('#results-tbody-telefonate').html('<tr><td colspan="6" class="text-center text-muted">Inserisci un termine o un intervallo di date</td></tr>');
            return;
        }

        lastQuery = { q, from, to };

        // Etichetta ultima ricerca
        let label = '';
        if (q)    label += 'Utente: <strong>' + $('<span>').text(q).html() + '</strong>';
        if (from) label += (label ? ' &nbsp;|&nbsp; ' : '') + 'Dal: <strong>' + from + '</strong>';
        if (to)   label += (label ? ' &nbsp;|&nbsp; ' : '') + 'Al: <strong>' + to + '</strong>';
        $('#last-query-label').html('<i class="fa-solid fa-tag"></i> Ultima ricerca: ' + label).show();

        $('#results-tbody-telefonate').html('<tr><td colspan="6" class="text-center"><i class="fa-solid fa-spinner fa-pulse"></i> Caricamento...</td></tr>');

        $.ajax({
            url: '../php/search_telefonate.php',
            type: 'GET',
            data: { q, from, to },
            dataType: 'html',
            success: function (r) { $('#results-tbody-telefonate').html(r); },
            error: function ()    { $('#results-tbody-telefonate').html('<tr><td colspan="6" class="text-center text-danger">Errore nella ricerca</td></tr>'); }
        });
    }

    $('#btn-search-telefonate').on('click', eseguiRicercaTelefonate);
    $('#btn-refresh-tel').on('click', function () {
        if (lastQuery.q || lastQuery.from || lastQuery.to) eseguiRicercaTelefonate();
    });
    $('#btn-reset-date').on('click', function () {
        $('#search-date-from, #search-date-to').val('');
        lastQuery.from = ''; lastQuery.to = '';
    });

    // =============================================
    // 12. RICERCA CONTRATTI
    // =============================================
    function eseguiRicercaContratti() {
        const termine = $('#search-input-contratti').val().trim();
        if (!termine) {
            $('#results-tbody-contratti').html('<tr><td colspan="6" class="text-center text-muted">Inserisci un numero o nominativo da cercare</td></tr>');
            return;
        }
        $('#results-tbody-contratti').html('<tr><td colspan="6" class="text-center"><i class="fa-solid fa-spinner fa-pulse"></i> Caricamento...</td></tr>');
        $.ajax({
            url: '../php/search_contratti.php',
            type: 'GET',
            data: { q: termine },
            dataType: 'html',
            success: function (r) { $('#results-tbody-contratti').html(r); },
            error: function ()    { $('#results-tbody-contratti').html('<tr><td colspan="6" class="text-center text-danger">Errore nella ricerca</td></tr>'); }
        });
    }

    $('#btn-search-contratti').on('click', eseguiRicercaContratti);
    $('#btn-refresh-contratti').on('click', function () {
        if ($('#search-input-contratti').val().trim()) eseguiRicercaContratti();
    });

    // =============================================
    // 13. RICERCA SIM
    // =============================================
    function eseguiRicercaSim() {
        const termine = $('#search-input-sim').val().trim();
        if (!termine) {
            $('#results-tbody-sim').html('<tr><td colspan="6" class="text-center text-muted">Inserisci un codice SIM o numero telefono</td></tr>');
            return;
        }
        $('#results-tbody-sim').html('<tr><td colspan="6" class="text-center"><i class="fa-solid fa-spinner fa-pulse"></i> Caricamento...</td></tr>');
        $.ajax({
            url: '../php/search_sim.php',
            type: 'GET',
            data: { q: termine },
            dataType: 'html',
            success: function (r) { $('#results-tbody-sim').html(r); },
            error: function ()    { $('#results-tbody-sim').html('<tr><td colspan="6" class="text-center text-danger">Errore nella ricerca</td></tr>'); }
        });
    }

    $('#btn-search-sim').on('click', eseguiRicercaSim);
    $('#btn-refresh-sim').on('click', function () {
        if ($('#search-input-sim').val().trim()) eseguiRicercaSim();
    });

    // =============================================
    // 14. MODIFICA TELEFONATA
    // =============================================
    $(document).on('click', '.btn-edit', function (e) {
        e.stopPropagation();
        const row = $(this).closest('.clickable-row');
        const tipo = row.data('tipo') || '';
        $('#edit-id').val(row.data('id-chiamata'));
        $('#edit-user-display').text((row.data('nome') + ' ' + row.data('cognome')).trim() + ' (' + row.data('tel') + ')');
        $('#edit-date').val(row.data('data'));
        $('#edit-time').val(row.data('ora'));
        $('#edit-duration').val(row.data('durata'));
        $('#edit-cost').val(row.data('costo'));
        var costoLabel = tipo === 'consumo' ? 'Costo (€) — 0 per consumo, modifica durata:' : 'Costo (€):';
        $('#edit-panel').find('label[for="edit-cost"]').text(costoLabel);
        $('#edit-panel').removeClass('hidden-element').hide().fadeIn(300);
        $('html, body').animate({ scrollTop: $('#edit-panel').offset().top }, 500);
    });

    $('#btn-cancel-edit').on('click', function () {
        $('#edit-panel').fadeOut(200, function () { $(this).addClass('hidden-element'); });
    });

    $('#form-edit-call').on('submit', function (e) {
        e.preventDefault();
        $.ajax({
            url: '../php/update_call.php',
            type: 'POST',
            data: {
                id_chiamata: $('#edit-id').val(),
                data:        $('#edit-date').val(),
                ora:         $('#edit-time').val(),
                durata:      $('#edit-duration').val(),
                costo:       $('#edit-cost').val()
            },
            dataType: 'html',
            success: function () {
                $('#edit-panel').fadeOut(200, function () { $(this).addClass('hidden-element'); });
                eseguiRicercaTelefonate();
            },
            error: function () { alert('Errore durante la modifica'); }
        });
    });

    // =============================================
    // 15. ELIMINA TELEFONATA
    // =============================================
    $(document).on('click', '.btn-delete', function (e) {
        e.stopPropagation();
        const row = $(this).closest('.clickable-row');
        $('#delete-id').val(row.data('id-chiamata'));
        $('#del-user').text((row.data('nome') + ' ' + row.data('cognome')).trim());
        $('#del-tel').text(row.data('tel'));
        $('#del-cost').text(row.data('costo') + ' €');
        $('#delete-response').html('');
        $('#delete-modal').removeClass('hidden-element');
    });

    $(document).on('click', '#btn-cancel-del, .modal-overlay', function (e) {
        if (e.target !== this && this.id !== 'btn-cancel-del') return;
        $('#delete-modal').addClass('hidden-element');
    });

    $('#form-delete-call').on('submit', function (e) {
        e.preventDefault();
        const tel = $('#del-tel').text();
        $.ajax({
            url: '../php/delete_call.php',
            type: 'POST',
            data: { id_chiamata: $('#delete-id').val() },
            dataType: 'json',
            success: function (data) {
                $('#delete-modal').addClass('hidden-element');
                if (data.ok) {
                    eseguiRicercaTelefonate();
                    mostraResiduo(tel);
                } else {
                    alert('Errore: ' + (data.msg || 'Sconosciuto'));
                }
            },
            error: function () { alert("Errore di connessione durante l'eliminazione"); }
        });
    });

    // =============================================
    // 16. TASTO INVIO per cercare
    // =============================================
    $('#search-input').on('keypress', function (e) {
        if (e.which === 13) eseguiRicercaTelefonate();
    });
    $('#search-input-sim').on('keypress', function (e) {
        if (e.which === 13) eseguiRicercaSim();
    });
    $('#search-input-contratti').on('keypress', function (e) {
        if (e.which === 13) eseguiRicercaContratti();
    });

});
