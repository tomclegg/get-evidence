var datatables_options = {
    'variant_table': {
	'bProcessing': true,
	'bAutoWidth': false,
	'aLengthMenu': [[5, 10, 25, 50, 100, -1], [5, 10, 25, 50, 100, "All"]],
	'iDisplayLength': -1,
	'aoColumnDefs': [{'aTargets': [1,2,3,4,5], 'sWidth': '10%'}]
    }
};
var datatables_objects = {};
var variant_table_showall = false;
var variant_table = false;

jQuery(document).ready(function($){
	$.fn.dataTableExt.afnFiltering.push(function(oSettings, aData, iDataIndex) {
		var t = datatables_objects["variant_table"];
		if (t && oSettings == t.fnSettings())
		    return variant_table_showall || aData[7];
		else
		    return true;
	    });
	function datatable_sort_enum (a,b,v) {
	    var aa = v.indexOf(a);
	    var bb = v.indexOf(b);
	    return aa-bb;
	}
	var enum_importance = ['Low', 'Moderate', 'High'];
	var enum_evidence = ['Uncertain', 'Likely', 'Well-established'];
	jQuery.fn.dataTableExt.oSort['importance-asc'] = function(a,b) {
	    return datatable_sort_enum (a,b,enum_importance);
	};
	jQuery.fn.dataTableExt.oSort['importance-desc'] = function(a,b) {
	    return datatable_sort_enum (b,a,enum_importance);
	};
	jQuery.fn.dataTableExt.oSort['evidence-asc'] = function(a,b) {
	    return datatable_sort_enum (a,b,enum_evidence);
	};
	jQuery.fn.dataTableExt.oSort['evidence-desc'] = function(a,b) {
	    return datatable_sort_enum (b,a,enum_evidence);
	};
	function datatable_render_freq(oObj){
	    var x = oObj.aData[oObj.iDataColumn];
	    if(x=='') return "?";
	    x=parseFloat(x)*100;
	    if(x>10) return x.toFixed(0)+'%';
	    if(x>1) return x.toFixed(1)+'%';
	    if(x>0.1) return x.toFixed(2)+'%';
	    if(x>0) return x.toFixed(3)+'%';
	    return 0;
	}
	$(".datatables_please").each(function(){
		var name = $(this).attr('datatables_name');
		var opts = {};
		if (name && typeof datatables_options[name] != 'undefined')
		    opts = datatables_options[name];
		if (typeof opts.bJQueryUI == 'undefined')
		    opts.bJQueryUI = true;
		if (typeof opts.aoColumnDefs == 'undefined')
		    opts.aoColumnDefs = [];
		opts.aoColumnDefs.push({'aTargets': ['Invisible'], 'bVisible': false});
		opts.aoColumnDefs.push({'aTargets': ['SortNumeric'], 'sType': 'numeric'});
		opts.aoColumnDefs.push({'aTargets': ['SortImportance'], 'sType': 'importance'});
		opts.aoColumnDefs.push({'aTargets': ['SortEvidence'], 'sType': 'evidence'});
		opts.aoColumnDefs.push({'aTargets': ['RenderFreq'], 'fnRender': datatable_render_freq, 'bUseRendered': false});
		var t = $(this).dataTable(opts);
		if (name)
		    datatables_objects[name] = t;
	    });
	
	function variant_table_update (ev) {
		variant_table_showall = $("#variant_table_showall").attr("checked");
		var t = datatables_objects["variant_table"];
		t.fnFilter(t.fnSettings().oPreviousSearch.sSearch);
	}
	$(".variant_table_updater").bind("change", variant_table_update);
	variant_table_update();
    });
