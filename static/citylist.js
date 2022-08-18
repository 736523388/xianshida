$(function(){
	
	$.ajaxSetup({async : false});
	
	var cur_pro = $('select[name=province]').data('value');
	var cur_city = $('select[name=city]').data('value');
	var cur_county = $('select[name=district]').data('value');
	$.get("/api/base.city/lists",{pid:0} ,function(result,status){
		if(status!='success')return;
		var _html='<option value="0">请选择</option>';
        $.each(result.data,function(index,obj){
        	if(obj.name==cur_pro){
                _html+='<option selected value="'+obj.id+'">'+obj.name+'</option>';
			}else{
                _html+='<option value="'+obj.id+'">'+obj.name+'</option>';
			}
		});
        $('select[name=province]').html(_html);
        window.form.render();
        if(cur_pro!=''){
            selectcity();
        }
	});

    form.on('select(province)', function(){
        $('select[name=district]').html('');
        selectcity();
    });

	function selectcity(){
		var pid = $('select[name=province]').val();
		$.get("/api/base.city/lists",{pid:pid}, function(result,status){
            if(status!='success')return;
            var _html='<option value="0">请选择</option>';
            $.each(result.data,function(index,obj){
                if(obj.name==cur_city){
                    _html+='<option selected value="'+obj.id+'">'+obj.name+'</option>';
                }else{
                    _html+='<option value="'+obj.id+'">'+obj.name+'</option>';
                }
            });
            $('select[name=city]').html(_html);
            window.form.render();
			if(cur_city!=''){
				selectcounty();
			}
		});
	}

    form.on('select(city)', function(){
		selectcounty();
	});
	
	function selectcounty(){
		var pid = $('select[name=city]').val();
		$.get("/api/base.city/lists",{pid:pid}, function(result,status){
            if(status!='success')return;
            var _html='<option value="0">请选择</option>';
            $.each(result.data,function(index,obj){
                if(obj.name==cur_county){
                    _html+='<option selected value="'+obj.id+'">'+obj.name+'</option>';
                }else{
                    _html+='<option value="'+obj.id+'">'+obj.name+'</option>';
                }
            });
            $('select[name=district]').html(_html);
            window.form.render();
		});
	}
});