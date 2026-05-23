$(document).ready(function(){

    console.log("JQuery collegato!")

    $('.theme-mode-container').on('click', function(){
        const body = $('body');
        body.toggleClass("light-mode");
        body.toggleClass("dark-mode");
    });

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
        } else{
            new_text = "Vuoi già partire con dei minuti iniziali?<br>Se si, seleziona quanti...";
            quantity_unit = "Minuti";
        }

        // Aggiorniamo i valori
        startup_request.html(new_text);
        span_unit.html(quantity_unit);

        optional_container.fadeIn(200);
    });

});