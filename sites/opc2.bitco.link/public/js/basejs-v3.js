$(document).ready(function(){
	$("input[type=submit]").keypress(function (e) {
		if (e.which == 13) {
			e.preventDefault();
		}
	});
	$('.datetimepicker').datetimepicker({
		sideBySide: true,
		format: "YYYY-MM-DD HH:mm:ss",
		minDate: moment()
	});	
	if($(".timepicker").length){
		var date = new Date();
		var str = ('0'+date.getHours()).substr(-2) + "." + ('0'+date.getMinutes()).substr(-2)
		$('.timepicker').on("focus", function(){			
			if($(this).val()=='') {
				$(this).val(str)
			}
		});
	}
	$(".mmyypicker").datetimepicker({
		sideBySide: true,
		viewMode: 'months',
		format: 'MM-YYYY'
	});
	if($("input.float").length){
		$("input.float").on("keypress keyup blur",function (event) {
			$(this).val($(this).val().replace(/[^0-9\.]/g,""));
			if (((event.which != 8 && event.which != 46) && $(this).val().indexOf(".") != -1) && (event.which < 48 || event.which > 57)) {
				event.preventDefault();
			}
		});
	}
	if($("input.integer").length){
		$("input.integer").on("keypress keyup blur",function (event) {
			$(this).val($(this).val().replace(/[^\d].+/, ""));
			if ((event.which < 48 || event.which > 57) && (event.which != 46 && event.which != 8)) {
				event.preventDefault();
			}
		});
	}

	$("input[type=submit]").click(function(){
		$("input[name=button]").val($(this).val());
	});
	// Manual http://clientjs.org/
	var client = new ClientJS(); // Create A New Client Object
	var fingerprint = client.getFingerprint(); // Get Client\'s Fingerprint
	var browser = client.getBrowser(); // Get Browser
	var browserVersion = client.getBrowserVersion(); // Get Browser Version
	var OS = client.getOS(); // Get OS Version
	var osVersion = client.getOSVersion(); // Get OS Version
	var isMobile = client.isMobile(); // Check For Mobile
	var screenPrint = client.getScreenPrint(); // Get Screen Print
	var isCookie = client.isCookie(); // Check For Cookies
	setTimeout(function(){
		document.getElementsByName("ssid")[0].value = fingerprint;
		document.getElementsByName("machine")[0].value = "Browser:"+browser+", Version:"+browserVersion+", OS:"+OS+", OSVer:"+osVersion+", isMobile:"+isMobile+", Screen:"+screenPrint+", UseCookie:"+isCookie;
	},200);
	$('#goTop').click(function(){
		$('body').animate({
			scrollTop: 0
		}, 500);
	});
	$('input[type=hidden]').each(function(){
		$(this).closest("div").addClass("hidden")
	});
	
	$(".tdscale").click(function(){						
		$("#"+$(this).attr("rel")).prop("checked", true).change()
		$(this).closest("tr").children("td").removeClass("scaleact")

		// Remove active from na
		if($(this).closest(".scalebg").find(".tdscale-na").length) {
			$(this).closest(".scalebg").find(".tdscale-na").removeClass("active")
		}
		$(this).addClass("scaleact")

		var ans = $(this).closest(".mrAnswerDiv")						
		if(ans.length) {
			ans.find("div.scale-click").html($(this).attr("title"))
		}
	});
	$(".tdscale-na").click(function(){
		$(this).closest(".scalebg").find("td.scaleact").removeClass("scaleact")
	});
		
	
	////////////////////////////////////////
	///* Validate */
	////////////////////////////////////////	
	

	////////////////////////////////////////
	///* Event */
	////////////////////////////////////////
	$(".form-item > .form-radio").on("click", function(){
		var prevval = $(this).closest(".form-item").find("input[type=radio]:checked").val()
		var newval = $("#"+$(this).attr("rel")).attr("value")
		if(prevval != newval) {
			$("#"+$(this).attr("rel")).prop("checked", true).change()
		}
		$(".form-radio[data-form="+$(this).attr("data-form")+"]").closest(".form-item").find(".form-radio").removeClass("active")
		$(this).addClass("active")		
	});
	$(".form-checkbox").on("click", function(){
		var chkbox = $("#"+$(this).attr("rel"))
		var nchkbox = $("#"+$(this).attr("rel")+"n")

		if(chkbox.is(":checked")) {		
			chkbox.prop("checked", false).change()
			nchkbox.prop("checked", true)
			$(this).removeClass("active")
		} else {
			// Inexclusive		
			if($(this).hasClass('inexclusive')){
				$("div.form-checkbox.exclusive[data-form="+$(this).attr("data-form")+"]").each(function(){
					$(this).find("input[type=checkbox][value='1']").prop("checked", false).change()
					$(this).find("input[type=checkbox][value='0']").prop("checked", true)
					$(this).removeClass("active")
				});
			}
			// Exclusive		
			if($(this).hasClass('exclusive')){
				$("div.form-checkbox[data-form="+$(this).attr("data-form")+"]").each(function(){
					$(this).find("input[type=checkbox][value='1']").prop("checked", false).change()
					$(this).find("input[type=checkbox][value='0']").prop("checked", true)
					$(this).removeClass("active")
				});
			}
			
			chkbox.prop("checked", true).change()
			nchkbox.prop("checked", false)
			$(this).addClass("active")
		}
	});
	$("input.other").on("click", function(e){
		e.stopPropagation();
	});
	$("input[type=radio]").on("change" ,function(){
		//// Open other box
		if($(this).closest(".form-item").find("div.other").length){
			var chd = $(this).closest(".form-item").find("div.other")
			chd.addClass("hidden")
			chd.find("input[type=text]").val("")
		}
		if($("#"+$(this).attr("id")+"_odiv").length) {
			if($(this).is(":checked")) {
				$("#"+$(this).attr("id")+"_odiv").removeClass("hidden");				
			}			
		}		
	});
	$("input[type=checkbox]").on("change", function(){
		//// Add Highlight
		if($(this).is(":checked")) {		
			$(this).closest(".form-checkbox").addClass("active")
		} else {
			$(this).parent().removeClass("active")
		}

		//// Open other box
		if($("#"+$(this).attr("id")+"_odiv").length) {
			if($(this).is(":checked")) {				
				$("#"+$(this).attr("id")+"_odiv").removeClass("hidden");				
			}
			else {
				$("#"+$(this).attr("id")+"_odiv").addClass("hidden");
				$("#"+$(this).attr("id")+"_oth").val("");
			}
		}
	});
	$(".select2").select2();
	$("textarea").focusout(function(data){
		var tbl = "survey_"+$("input[name=stype]").val();
		var name = $(this).attr("name");
		var val = $(this).text();
		var idqr = $("input[name=qid]").val();
	});
	$("input.form-inputtext").blur(function(data){
		var tbl = "survey_"+$("input[name=stype]").val();
		var name = $(this).attr("name");
		var val = $(this).val();
		var idqr = $("input[name=qid]").val();
	});

	
	$('.mrAnswerDiv').scroll(function() {
		var scrollTop = $(this).scrollTop();
		$('.grid-header').css('transform', 'translateY(0px)');
	});
});
