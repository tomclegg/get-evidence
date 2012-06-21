var datatables_options = {
    'variant_table': {
	'bProcessing': true,
	'bAutoWidth': false,
	'aLengthMenu': [[5, 10, 25, 50, 100, -1], [5, 10, 25, 50, 100, "All"]],
	'iDisplayLength': -1,
	'aoColumnDefs': [{'aTargets': [1,2,4,5], 'sWidth': '10%'},
			 {'aTargets': [4], 'iDataSort': 3}]
    },
    'variant_table_insuff': {
	'bProcessing': true,
	'bAutoWidth': false,
	'aaSorting': [[2,'desc'], [3,'asc']],
	'aLengthMenu': [[5, 10, 25, 50, 100, -1], [5, 10, 25, 50, 100, "All"]],
	'iDisplayLength': 100,
	'aoColumnDefs': [{'aTargets': [1,2,3], 'sWidth': '10%'}]
    },
    'variant_table_coverage': {
	'bProcessing': true,
	'bAutoWidth': false,
	'aaSorting': [[3,'asc']],
	'aLengthMenu': [[5, 10, 25, 50, 100, -1], [5, 10, 25, 50, 100, "All"]],
	'iDisplayLength': -1
    },
    'variant_table_genereport': {
	'bProcessing': true,
        'bAutoWidth': false,
	'aaSorting': [[0, 'desc']],
        'aLengthMenu': [[5, 10, 25, 50, 100, -1], [5, 10, 25, 50, 100, "All"]],
        'iDisplayLength': -1,
	'aoColumnDefs': [{'aTargets': [1,5], 'sWidth': '15%'},
                         {'aTargets': [2,4], 'sWidth': '10%'},
                         {'aTargets': [3], 'sWidth': '5%'}]
    }
};
var datatables_objects = {};
var variant_table_showall = false;
var variant_table = false;
var variant_table_filter = function(){return true;};
var variant_table_filters =
    [   function(oSettings, aData, iDataIndex) {
	    return (!(aData[5]>0.025) || /likely|well-established/i.exec(aData[4])) && /pathogenic/i.exec(aData[4]);
	},
	function(oSettings, aData, iDataIndex) {
	    return true;
	}
	];

jQuery(document).ready(function($){
	$.fn.dataTableExt.afnFiltering.push(function(oSettings, aData, iDataIndex) {
		var t = datatables_objects["variant_table"];
		if (t && oSettings == t.fnSettings())
		    return variant_table_filter(oSettings, aData, iDataIndex);
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
	var enum_chromosome = ['1','chr1','2','chr2','3','chr3','4','chr4','5','chr5','6','chr6','7','chr7','8','chr8','9','chr9','10','chr10','11','chr11','12','chr12','13','chr13','14','chr14','15','chr15','16','chr16','17','chr17','18','chr18','19','chr19','20','chr20','21','chr21','22','chr22','X','Y'];
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
	jQuery.fn.dataTableExt.oSort['chromosome-asc'] = function(a,b) {
	    return datatable_sort_enum (a,b,enum_chromosome);
	};
	jQuery.fn.dataTableExt.oSort['chromosome-desc'] = function(a,b) {
	    return datatable_sort_enum (b,a,enum_chromosome);
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
		opts.aoColumnDefs.push({'aTargets': ['SortChromosome'], 'sType': 'chromosome'});
		opts.aoColumnDefs.push({'aTargets': ['SortImportance'], 'sType': 'importance'});
		opts.aoColumnDefs.push({'aTargets': ['SortEvidence'], 'sType': 'evidence'});
		opts.aoColumnDefs.push({'aTargets': ['SortDescFirst'], 'asSorting': ['desc','asc']});
		opts.aoColumnDefs.push({'aTargets': ['Unsortable'], 'bSortable': false});
		opts.aoColumnDefs.push({'aTargets': ['RenderFreq'], 'fnRender': datatable_render_freq, 'bUseRendered': false});
		var t = $(this).dataTable(opts);
		if (name)
		    datatables_objects[name] = t;
	    });
	
	function variant_table_update (ev) {
	    for (var i=0; i<variant_table_filters.length; i++) {
		if (jQuery("#variant_filter_radio"+i).attr("checked")) {
		    variant_table_filter = variant_table_filters[i];
		    var t = datatables_objects["variant_table"];
		    t.fnFilter(t.fnSettings().oPreviousSearch.sSearch);
		}
	    }
	    return true;
	}
	$("#variant_filter_radio input").bind("change", variant_table_update);
	$("#variant_filter_radio").buttonset();
	variant_table_update();
	$("#variant_table_tabs").tabs();
	var rows_to_add = [];
	var but_not_before = 0;
	if (datatables_objects['variant_table_insuff']) {
	    $.ajax($('[datatables_name=variant_table_insuff]').attr('ajax_source'),
		   {
		       success : function(d,t,r) {
			   datatables_objects['variant_table_insuff'].
			       fnClearTable();
			   rows_to_add = $.makeArray($(d).find('tbody tr'));
		       }
		   });
	    $('#variant_table_tabs .ajax_loader_image').removeClass('ui-helper-hidden');
	}
	window.setInterval(function() {
		var endtime;
		var starttime = new Date().valueOf();
		if (but_not_before > starttime)
		    return;
		but_not_before = starttime + 30;
		if (rows_to_add.length > 0) {
		    var todo = rows_to_add.splice(0, 100);
		    var aData = $.map(todo, function(e,i){
			    return [$.makeArray
				    ($(e).
				     find('td').
				     map(function(i,e) {
					     return e.innerHTML;
					 }))];
			});
		    datatables_objects['variant_table_insuff'].
			fnAddData(aData, rows_to_add.length == 0);
		    if (rows_to_add.length == 0) {
			$('#variant_table_tabs .ajax_loader_image').addClass('ui-helper-hidden');
		    }
		}
		endtime = new Date().valueOf();
		but_not_before = endtime + Math.max(200, endtime - starttime);
	    }, 10);
    });
