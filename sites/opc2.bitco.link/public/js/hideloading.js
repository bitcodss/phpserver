$(window).ready(function() {
	$("#loading").fadeOut(300);

	$("#submit, #back").click(function(){
		$("#loading").fadeIn(300);
	});
});