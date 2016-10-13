/*************** lib.js ***************/
var console = window.console || {log: function(){}};
var ipad = navigator.userAgent.match(/iPad/i) ? true : false;
var pantallas = [];
var sonidos = {};
var botones = {};
var pantalla_inicial = 0;
var estaPantalla;
var enTransicion;
var instruccion;
var SVG;

/************** Pantallas Inicializacion **************/
$(document).ready(function(){
	/*// pantallas
	$('#panelReel').find('seccion').each(function(index){
		var pantalla = $(this);
		pantalla.indice = index;
		pantalla.attr('indice', index).css({'position': 'absolute', 'width': '790', 'height': '560', 'opacity': '0', 'left': '0', 'top': '0'}).hide();
		pantallas.push(pantalla); 
    });
	instruccion = $('#instrucciones');
	//
	$('#reiniciar').unbind('click');
	$('#reiniciar').on('mousedown touchstart',function(){
		if(enTransicion) return;
		var pantalla = pantallas[estaPantalla];
		if(pantalla && pantalla.reiniciar) pantalla.reiniciar();
	});*/
	// sonidos
	/*var sonidos_list = {
		// "nombre_sonido"	: "nombre_archivo"
		"incorrecta"		: "audio/error",		
		"correcta"			: "audio/final",		
		"select"			: "audio/select",		
		"p2_audio"			: "audio/p2_audio",		
		"p3_audio"			: "audio/p3_audio",		
		"p4_audio"			: "audio/p4_audio",		
		"p5_audio"			: "audio/p5_audio",		
		"p6_audio"			: "audio/p6_audio"
	};
	$.each(sonidos_list, function(key){
		eval('sonidos["' + key + '"] = new buzz.sound("' + this + '", {formats: ["mp3"]});');
	});
	//
	$('#headerTab').click(iniciar_actividad);
	SVG = Snap('#d2-4-2mt1').attr({display: 'none'});*/
});

/************** Carcaza **************/

function ir_a(pantalla, efecto, args){
	enTransicion = true;
	var pantallaAnterior = pantallas[estaPantalla];
	var pantallaSiguiente = pantallas[estaPantalla = pantalla];
	if(!pantallaSiguiente) return;
	var args = args || {};
	if(pantallaSiguiente.preparar) pantallaSiguiente.preparar();
	//instruccion.html(pantallaSiguiente.instruccion);
	switch(efecto){
		case 'fade':
		if(pantallaAnterior){
			pantallaAnterior.velocity({'opacity': '0'}, 300, function(){
				pantallaAnterior.hide();
				if(pantallaAnterior.terminar) pantallaAnterior.terminar();
				pantallaSiguiente.show().velocity({'opacity': '1'}, 300, fin);
			});
		}else{;
			pantallaSiguiente.show().velocity({'opacity': '1'}, 600, fin);
		};
		break;
		
		case 'slide':
		var dir = args.dir || 1;
		var dx = 790 * dir;
		var dy = 560 * dir;
		//pantallaSiguiente.css({'left': '+=' + dx});
		if(pantallaAnterior){
			pantallaAnterior.velocity({'left': '-=' + dx}, 1000, function(){
				pantallaAnterior.css({'opacity': '1', 'left': '0'}).hide();
				//pantallaSiguiente;
				if(pantallaAnterior.terminar) pantallaAnterior.terminar();
				//pantallaSiguiente.show().velocity({'opacity': '1'}, 300, fin);
			});
		}else{;
			//pantallaSiguiente.show().velocity({'opacity': '1'}, 600, fin);
		};
		pantallaSiguiente.css({'opacity': '1', 'left': '+=' + dx}).show().velocity({'left': '0'}, 1000, fin);
		
		
		break;
		
		default:
		if(pantallaAnterior){
			pantallaAnterior.css({'opacity': '0'}).hide();
			if(pantallaAnterior.terminar) pantallaAnterior.terminar();
		};
		pantallaSiguiente.show().css({'opacity': '1'});
		fin();
	};
	
	function fin(){
		show_instruccion();
		if(pantallaSiguiente.comenzar) pantallaSiguiente.comenzar();
		enTransicion = false;
	};
	
	function show_instruccion(){
		var ins = paneles[estaPantalla].instrucciones || '';
		instruccion.html(ins);
		if(pantallaSiguiente.visitada) return;
		pantallaSiguiente.visitada = true;
		//
		if (footerImg.attr('src') == footerOff) {
			footerImg.attr('src', footerOn)
			$('footer').stop(true, true).animate({
				bottom: '+=' + footerHeight
			}, 500, 'swing', function () {
				$('footer').delay(10000).animate({
					bottom: '-=' + footerHeight
				}, 500, 'swing', function () {
					footerImg.attr('src', footerOff)
				});
			});
		};
	};
};
/************** Funciones Globales **************/
function random_array(array){	//desordena un array
	var temp_array = [];
	var rand_array = [];
	for(var i=0; i<array.length; i++){
		temp_array.push(i);
	}
	while(temp_array.length > 0){
		var indice = Math.floor(Math.random() * temp_array.length);
		rand_array.push(array[temp_array[indice]]);
		temp_array.splice(indice, 1);
	}
	return rand_array;
};	
	
function prevent(event){ //previene las acciones por defecto en el navegador
	event.preventDefault();
};
	
function close_pop(event){	//cerrar ventana emergente
	event.preventDefault();
	var este = $(this);
	este.velocity({'opacity': '0'}, 600, function(){
		este.hide();
	});
	return false;
};
	
function close_pop_2(event){	//cerrar ventana emergente
	event.preventDefault();
	var este = $(this).parent();
	este.velocity({'opacity': '0'}, 600, function(){
		este.hide();
	});
	return false;
};
	
function limit_tab(event){
	if (event.keyCode == 9){
		event.preventDefault();
	};
};
	
function signo(numero){
	if (numero == 0) return 0;
	if (numero > 0) return 1;
	if (numero < 0) return -1;
	return null;
};
	
function rect_intersects_rect(rect_1, rect_2){
	if((rect_1.x <= rect_2.x && rect_2.x <= (rect_1.x + rect_1.w))
		|| (rect_1.x <= (rect_2.x + rect_2.w) && (rect_2.x + rect_2.w) <= (rect_1.x + rect_1.w))){
		if(rect_2.y <= rect_1.y && rect_1.y <= (rect_2.y + rect_2.h)){
			return true;
		};
		if(rect_2.y <= (rect_1.y + rect_1.h) && (rect_1.y + rect_1.h) <= (rect_2.y + rect_2.h)){
			return true;
		};
	};
	if((rect_1.y <= rect_2.y && rect_2.y <= (rect_1.y + rect_1.h))
		|| (rect_1.y <= (rect_2.y + rect_2.h) && (rect_2.y + rect_2.h) <= (rect_1.y + rect_1.h))){
		if(rect_2.x <= rect_1.x && rect_1.x <= (rect_2.x + rect_2.w)){
			return true;
		};
		if(rect_2.x <= (rect_1.x + rect_1.w) && (rect_1.x + rect_1.w) <= (rect_2.x + rect_2.w)){
			return true;
		};
	};
	/*if(rect_contains_rect(rect_1, rect_2) || rect_contains_rect(rect_2, rect_1)){
		return true;
	};*/
	return false;
};
