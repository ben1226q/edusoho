webpackJsonp(["app/js/quiz-question/my-favorite/index"],{"0b27487e59b99cd808fa":function(n,t,i){"use strict";$("body").on("click",".showQuestion",function(){$(this).parent().find(".panel").toggle()}),$("body").on("click",".unfavorite-btn",function(){$btn=$(this),$.post($(this).data("url"),function(){$btn.parents("tr").hide()})})}},["0b27487e59b99cd808fa"]);