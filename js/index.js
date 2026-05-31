$(document).ready(function(){

    console.log("JQuery collegato e pronto per il Back-Office!");

    // 1. GESTIONE CAMBIO PAGINA DA NAVBAR (Click-driven)
    $('.nav-link').on('click', function(e){
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
    $('.theme-mode-container').on('click', function(){
        const body = $('body');
        body.toggleClass("light-mode").toggleClass("dark-mode");
    });

    // 3. SELEZIONE TIPO CONTRATTO (Vecchio script mantenuto e integrato)
    $('.type-select').on('change', function(){
        const startup_request = $('.startup-request');
        const span_unit = $('.quantity-unit');
        const current_selection = $(this).val();
        const optional_container = $('.optional-container');

        let new_text = "";
        let quantity_unit = "";

        if(current_selection == 'ricarica'){
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
    $('.clickable-row').on('click', function(e){
        // Evitiamo che il click sui pulsanti interni attivi questo handler
        if ($(e.target).closest('.btn-action').length) return;

        const nome = $(this).data('nome');
        const cognome = $(this).data('cognome');
        const tel = $(this).data('tel');
        
        alert("Dettagli Contratto Selezionato:\nCliente: " + nome + " " + cognome + "\nTelefono: " + tel + "\nStato SIM: Attiva");
    });

    // 5. AZIONE MODIFICA (Matita)
    $('.btn-edit').on('click', function(e){
        e.stopPropagation(); // Blocca propagazione alla riga
        const row = $(this).closest('.clickable-row');
        
        // Estrazione dati dai data-attributes della riga
        const id = row.data('id-chiamata');
        const nome = row.data('nome');
        const cognome = row.data('cognome');
        const tel = row.data('tel');
        const data = row.data('data');
        const ora = row.data('ora');
        const durata = row.data('durata');
        const costo = row.data('costo');

        // Popolamento form di modifica
        $('#edit-id').val(id);
        $('#edit-user-display').text(nome + " " + cognome + " (" + tel + ")");
        $('#edit-date').val(data);
        $('#edit-time').val(ora);
        $('#edit-duration').val(durata);
        $('#edit-cost').val(costo);

        // Mostra il pannello con effetto scorrimento
        $('#edit-panel').removeClass('hidden-element').hide().fadeIn(300);
        
        // Scroll automatico verso il form di modifica per comodità dell'utente
        $('html, body').animate({
            scrollTop: $("#edit-panel").offset().top
        }, 500);
    });

    // Annulla Modifica
    $('#btn-cancel-edit').on('click', function(){
        $('#edit-panel').fadeOut(200, function(){
            $(this).addClass('hidden-element');
        });
    });

    // 6. AZIONE ELIMINA (Cestino) -> Apre Modale di Conferma
    $('.btn-delete').on('click', function(e){
        e.stopPropagation();
        const row = $(this).closest('.clickable-row');
        
        const id = row.data('id-chiamata');
        const nome = row.data('nome');
        const cognome = row.data('cognome');
        const tel = row.data('tel');
        const costo = row.data('costo');

        // Popola la modale
        $('#delete-id').val(id);
        $('#del-user').text(nome + " " + cognome);
        $('#del-tel').text(tel);
        $('#del-cost').text(costo + " €");

        // Mostra modale
        $('#delete-modal').removeClass('hidden-element');
    });

    // Chiudi modale eliminazione
    $('#btn-cancel-del, .modal-overlay').on('click', function(e){
        if (e.target !== this && this.id !== 'btn-cancel-del') return; // Evita chiusura cliccando dentro il box
        $('#delete-modal').addClass('hidden-element');
    });

    // 7. SIMULAZIONE RICERCA (Facoltativa, per mostrare interazione immediata)
    $('#btn-search').on('click', function(){
        const valore = $('#search-input').val().toLowerCase();
        if(valore === "") {
            $('#results-tbody tr').show();
            return;
        }
        
        $('#results-tbody tr').each(function(){
            const rigatesto = $(this).text().toLowerCase();
            if(rigatesto.indexOf(valore) === -1) {
                $(this).hide();
            } else {
                $(this).show();
            }
        });
    });
});