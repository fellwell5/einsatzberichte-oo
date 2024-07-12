jQuery( document ).ready(function() {
  function loadYear(){
  	jQuery("#eib_jahresuebersicht_preloader").show();
  	var year = jQuery( "#eib_jahresuebersicht_select" ).val();
  	console.log("Lade Jahr: "+year);
  	jQuery.get( ajax_object.getYear, { eib_year: year}, function( data ) {
		  jQuery( "#eib_jahresuebersicht_container" ).html(data);
  		jQuery("#eib_jahresuebersicht_preloader").hide();
		});
  }
  jQuery( "#eib_jahresuebersicht_select" ).change(function() {
	  loadYear();
	});
  loadYear();
});