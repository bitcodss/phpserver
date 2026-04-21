$(function(){
	var lang = 'eng';
	var actlang = 'tha';
	if($.cookie('lang')=='eng') {
		lang = 'tha'
		actlang = 'eng'
		$("div.lang").attr("rel","eng")

		$("#back").val("Back")
		if($("#submit").val()=="ถัดไป" || $("#submit").val()=="Next") {
			$("#submit").val("Next")
		} else {
			$("#submit").val("Submit")
		}
	}
	$(lang).addClass("hidden")
	$("div."+lang).addClass("hidden")
	$("#"+actlang).addClass("active")

	$("#tha").click(function(){
		var t = $(this).closest(".lang")
		
		$("tha").removeClass("hidden")
		$("eng").addClass("hidden")
		$("div.tha").removeClass("hidden")
		$("div.eng").addClass("hidden")

		t.attr("rel","tha")
		$("#eng").removeClass("active")
		$("#tha").addClass("active")

		$("#back").val("ย้อนกลับ")
		if($("#submit").val()=="ถัดไป" || $("#submit").val()=="Next") {
			$("#submit").val("ถัดไป")
		} else {
			$("#submit").val("ส่งคำตอบ")
		}
		
		$.cookie('lang', "tha", { expires: 7 });

	});
	$("#eng").click(function(){
		var t = $(this).closest(".lang")
		
		$("eng").removeClass("hidden")
		$("tha").addClass("hidden")
		$("div.eng").removeClass("hidden")
		$("div.tha").addClass("hidden")

		t.attr("rel","eng")
		$("#tha").removeClass("active")
		$("#eng").addClass("active")

		$("#back").val("Back")
		if($("#submit").val()=="ถัดไป" || $("#submit").val()=="Next") {
			$("#submit").val("Next")
		} else {
			$("#submit").val("Submit")
		}

		$.cookie('lang', "eng", { expires: 7 });

	});
});