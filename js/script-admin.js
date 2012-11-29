jQuery(document).ready(function($){
//~ 

function check_update(){
	//~ alert(init_val);
	
		        $.ajax({
            type :  "post",
            url : ajaxurl,
            timeout : 1999,
            async: false,
            data : {
                'action' : 'db_progress',
                'id' : 'check',
                'init_val': init_val		  
            },			
            success :  function(data){  
		     

                if(data == 'done' ){
					$("#db-pbar").progressbar("option","value", 100);
					clearInterval(int);
					$("#db-pbar").fadeOut("slow").text("Complete").hide().fadeIn('slow').fadeOut("slow");
					$("#db-pbar").progressbar('destroy');
					$.ajax({
						type :  "post",
						url : ajaxurl,
						timeout : 5000,
						dataType: "json",
						async: false,
						data : {
							'action' : 'db_update_done'		  
						},			
						success :  function(data){   
							data = Object(data);   
							$('#cb_tot').fadeOut('slow').text(data.tot).fadeIn('slow');
							$('#cb_last').fadeOut('slow').text(data.last).fadeIn('slow');
							//~ alert(init_val);
						

						}
				})
					//~ $("#db-pbar").fadeOut("slow").text("Complete").hide().fadeIn('slow').fadeOut("slow");
					//~ $("#db-pbar").fadeOut("slow");
					
				}else if(data == 0){
				 $("#db-pbar").children().html("<span style='margin:1px auto;font-size:10px;'>Downloading</span>");	
				}
				else{
					$("#db-pbar").children().html("");	
					$("#db-pbar").progressbar("option","value", Number(data));
				}
				  
            }
        })	//end of ajax
	
}

$('#cbdbupdate').click(function(){
	$("#db-pbar").html(null);
	$("#db-pbar").show();
	 $("#db-pbar").progressbar({ value: 20 });
	
	        $.ajax({
            type :  "post",
            url : ajaxurl,
            timeout : 5000,
            async :false,
            data : {
                'action' : 'db_progress',
                'id' : 'ini'		  
            },			
            success :  function(data){      
				init_val = data;
				//~ alert(init_val);
                int = setInterval(check_update,2000);

            }
        })	//end of ajax	
        
        return false;
        
	})
	

$('#cbcmcheck').click(function(){
	var checked = $('#cbcmcheck:checked').length > 0 ;
	if(checked){
		var adnum = Number($('input[name="cmpadnum"]').val());
		if(adnum == 0){
			alert("You must put the number of ads in the above field");
			return;
		}
		//$('#customadbox tr:last').after('<tr>  </tr>');
		for( var i= 0;i<adnum;i++)
			$('#hiddentablerow').clone().appendTo('#customadbox').fadeIn('show');
		
		$('#addanewrow').fadeIn('slow');
		$('#randomcheckbox').fadeIn('slow');
		
		
		}else{
			
			$('#customadbox').find("tr:gt(0)").fadeOut('slow').remove();
			$('#addanewrow').fadeOut('slow');
		$('#randomcheckbox').fadeOut('slow');
	
	    }
	
	
	
	})
	
	
$('#addanewrow').click(function(){	
	$('#hiddentablerow').clone().appendTo('#customadbox').fadeIn('show');
	
	})
	
})
