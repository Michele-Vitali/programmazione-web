$(document).ready(function(){

    console.log("JQuery collegato!")

    $('.theme-mode-container').on('click', function(){
        const body = $('body');
        body.toggleClass("light-mode");
        body.toggleClass("dark-mode")
    });

});