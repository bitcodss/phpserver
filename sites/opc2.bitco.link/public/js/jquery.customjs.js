(function($) {
	// ประกาศ plugin
	// How to use:
	// $(this).errorShow({ title:"", text:"" });

   $.fn.showOther = function( option ){
				var $this = $(this);
				var opts = $.extend({}, $.fn.showOther.defaults, option);
			return this.each(function(){
				$this.bind("change", function(){
					$target = $("#"+opts.ename+"_odiv")
					
					if($target.attr("rel")=="on") {
						$target.fadeOut();
						$target.attr({ rel:"off" })
						$("#"+opts.ename+"_oth").val("");
					}
					else if($target.attr("rel")=="off") {
						$target.fadeIn();
						$target.attr({ rel:"on" })
					}
				});
			});
		$.fn.showOther.defaults = {
			ename : ""
		};
   };
   $.fn.showQuestion = function( option ){
				var $this = $(this);
				var opts = $.extend({}, $.fn.showQuestion.defaults, option);
			
				$this.change(function(){
					var content = opts.ename.split(';');
					for(i=0;i<content.length;i++){
						$target = $('#'+content[i])
						$target.fadeIn();
					}
				});
			
		$.fn.showQuestion.defaults = {
			ename : ""
		};
   };
   $.fn.hideQuestion = function( option ){
				var $this = $(this);
				var opts = $.extend({}, $.fn.hideQuestion.defaults, option);
			
				$this.change(function(){
					var content = opts.ename.split(';');
					var rmv = opts.eq.split(';');
					for(i=0;i<content.length;i++){
						$target = $('#'+content[i])
						$target.fadeOut();
					}
					if(opts.eq != false){
						for(x=0;x<rmv.length;x++){
							var r = rmv[x].split(':');
							if(r[1]=="radio") $("input[name="+r[0]+"]").prop("checked", false);
							if(r[1]=="checkbox") $("#"+r[0]).prop("checked", false);
							if(r[1]=="text") $("#"+r[0]).val("");
						}
					}
				});
			
		$.fn.hideQuestion.defaults = {
			ename : "",
			eq : ""
		};
   };
  $.fn.showhideQuestion = function( option ){
				var $this = $(this);
				var opts = $.extend({}, $.fn.hideQuestion.defaults, option);
			return this.each(function(){
				$this.bind("change", function(){
					var content = opts.ename.split(';');
					var rmv = opts.eq.split(';');
					for(i=0;i<content.length;i++){
						$target = $('#'+content[i])
						if($this.is(":checked")){
							$target.fadeIn();
						}
						else {
							$target.fadeOut();
							if(opts.eq != false){
								for(x=0;x<rmv.length;x++){
									var r = rmv[i].split(',');
									if(r[1]=="radio") $("#"+r[0]).prop("checked", false);
									if(r[1]=="checkbox") $("#"+r[0]).prop("checked", false);
									if(r[1]=="text") $("#"+r[0]).val("");
								}
							}
						}
					}
				});
			});
		$.fn.hideQuestion.defaults = {
			ename : "",
			eq : ""
		};
   };
   $.fn.containsAny = function( option ){
				var $this = $(this);
				var opts = $.extend({}, $.fn.containsAny.defaults, option);

				var contain = opts.resp.split(',');
				var bool = false;
				$('input[name="'+$this.attr("name")+'"]:checked').each(function(){
					for(i=0;i<contain.length;i++){
						if ($(this).val() === contain[i] )
						{
							bool = true;
						}
					}
				});
				return bool;

		$.fn.containsAny.defaults = {
			data : ""
		};
   };
   $.fn.containsAll = function( option ){
				var $this = $(this);
				var opts = $.extend({}, $.fn.containsAll.defaults, option);

				var contain = opts.resp.split(',');
				var bool = true;
				$('input[name="'+$this.attr("name")+'"]:checked').each(function(){
					for(i=0;i<contain.length;i++){
						if ($(this).val() != contain[i] )
						{
							bool = false;
						}
					}
				});
				return bool;

		$.fn.containsAll.defaults = {
			data : ""
		};
   };
   $.fn.validateForm = function( option ){
				var $this = $(this);
				var opts = $.extend({}, $.fn.validateForm.defaults, option);

				var contain = opts.data.split(';');
				var valid = false;
				var bool = true;		
				// Hide all error DIVs
				$(document).hideError();

					for(i=0;i<contain.length;i++){

						var c = contain[i].split(':');				

						if (c[1]=="radio"){
							var fields = $("input[name='"+c[0]+"']:checked").val(); 
							if (!fields){
								bool = false;
								$(document).addnewError({ text:"Please fill the answer.", div: c[0] });
							}
						}
						if (c[1]=="checkbox"){
							var v = c[0].split('-')
							var bb = false
							for(y=0;y<v.length-1;y++){
								fields = $("input[name='"+v[y]+"']:checked").val(); 
								if (fields == 1){
									bb = true
								}
							}
							if(!bb) {
								bool = false;
								$(document).addnewError({ text:"Please fill the answer.", div: v[v.length-1] });
							}
						}
						if (c[1]=="text"){
							if($('textarea#'+c[0]).attr("value") == ""){
								bool = false;
								$(document).addnewError({ text:"Please fill the answer.", div: c[0] });
							}
						}
						if (c[1]=="input"){
							if($('input#'+c[0]).val() == ""){
								bool = false;
								alert("Please specify...")
							}
						}
					}
					// Scroll to Top
						$(document).scrollToErr();
						
				return bool;

		$.fn.validateForm.defaults = {
			data : ""
		};
   };
   $.fn.addnewError = function( option ){
				var $this = $(this);
				var opts = $.extend({}, $.fn.addnewError.defaults, option);
			return this.each(function(){

				$('#'+opts.div+'_errinfo').text(opts.text);				
				$('#'+opts.div+'_err').fadeIn(1000);
				// $('#'+opts.div+'_err').scrollToErr();

			});
		$.fn.addnewError.defaults = {
			text : "",
			div : ""
		};
   };
   $.fn.hideError = function( option ){
				var $this = $(this);
				var opts = $.extend({}, $.fn.hideError.defaults, option);
			return this.each(function(){

				$('.span_error').text("");
				$('div.errors').hide();				

			});
		$.fn.hideError.defaults = {
			text : "",
			div : ""
		};
   };
   $.fn.scrollToErr = function( option ){
				var $this = $(this);
				var opts = $.extend({}, $.fn.scrollToErr.defaults, option);
			return this.each(function(){

						  // Scroll
						$("html,body").animate({
							scrollTop: $('body').offset().top},
							"slow");			

			});
		$.fn.scrollToErr.defaults = {
			// Nothing
		};
   };

})(jQuery);