// reads in tab-separated table
// first line of the file should be the column headers
// We expect the following fields: 
// "gene" (String), "aa_change" (String), "impact" (String),
// "inheritance" (String), "dbsnp_id" (String), "overall_frequency_n" (Integer >= 0), 
// "overall_frequency_d" (Integer >= 0), "overall_frequency" (Float >=0 && <= 1), 
// "qualityscore_in_silico" (String), "qualityscore_in_vitro" (String), 
// "qualityscore_case_control" (String), "qualityscore_familial" (String),
// "qualityscore_clinical" (String), "max_or_disease_name" (String)
// "max_or_case_pos" (Integer >=0), "max_or_case_neg" (Integer >=0), "max_or_control_pos" (Integer >=0)
// "max_or_control_neg" (Integer >=0), "max_or_or" (Float > 0)


EvidenceData ReadTable (String filename) {
  data = new EvidenceData();
  
  String[] rows = loadStrings(filename);
  
  // get headers and corresponding columns
  String[] header = split(rows[0], '\t');
  for (int i = 0; i < header.length; i++) {
    data.addColumn(header[i],i);
    //println("Added column " + header[i]);
  }
  
  // start reading at row 1, because first row was column headers
  for (int i = 1; i < rows.length; i++) {
    // skip if empty
    if (trim(rows[i]).length() == 0) {
      continue;
    }

    String[] pieces = split(rows[i], '\t');  // split the row on tabs
    
    // Print warning if there aren't as many entries as the header, probably a problem in the file
    if (pieces.length != header.length) {
      println("ERROR: Less than entries in row unequal to the header, row number " + i);
    }
    
    // Get gene & change data
    String gene_ID = pieces[data.getColumn("gene")];
    String change = pieces[data.getColumn("aa_change")];
    
    // Get odds ratio, skip this variant if it's empty
    String odds_ratio_string = pieces[data.getColumn("max_or_or")];
    float odds_ratio;
    if (odds_ratio_string.length() < 1) {
      continue;
    } else {
      odds_ratio = parseFloat(pieces[data.getColumn("max_or_or")]);
    }
    
    // Set frequency, if 0 estimate as 0.5 if missing try to use OR data to estimate, if that's also missing default to "-1"
    String frequency_string = pieces[data.getColumn("overall_frequency")];
    float frequency;    
    boolean is_frequency_estimated = false;
    if (frequency_string.length() > 0) {
      frequency = parseFloat(pieces[data.getColumn("overall_frequency")]);
      if (frequency > 0) {
      } else {
        int pos_observations = parseInt( pieces[data.getColumn("overall_frequency_n")] );
        int total_observations = parseInt( pieces[data.getColumn("overall_frequency_d")] );
        if (pos_observations > 0) {
          println("ERROR: frequency is zero but positive observations nonzero for row " + i);
        } else {
          if (total_observations > 0) {
            frequency = 0.5 / total_observations;
            is_frequency_estimated = true;
            //println("Estimating frequency from total observations as " + frequency);
          } else {
            frequency = -1;
          }
        }
      }
    } else {
      if (odds_ratio_string.length() > 0) {
        int control_pos = parseInt( pieces[data.getColumn("max_or_control_pos")] );
        int control_neg = parseInt( pieces[data.getColumn("max_or_control_neg")] );
        if (control_pos > 0) { 
          frequency = control_pos * 1.0 / (control_pos + control_neg);
          is_frequency_estimated = true;
          //println("Calculated frequency from controls: " + frequency);
        } else {
          frequency = 0.5 / (control_pos + control_neg);
          is_frequency_estimated = true;
          //println("Estimated frequency from control: " + frequency);
        }
      } else { 
        frequency = -1;  // frequency should be between zero and one, so this value represents "no information"
      }
    }
    
    // Create variant with relevant data
    // Currently only do this for variants with odds ratio and frequency information
    if (odds_ratio > 0 && frequency > 0) {
      if (frequency > 0.5) {
        frequency = 1 - frequency;
        odds_ratio = 1 / odds_ratio;
      }
      Variant new_variant = new Variant(gene_ID, change);
      new_variant.frequency = frequency;
      new_variant.odds_ratio = odds_ratio;
      new_variant.freq_est = is_frequency_estimated;
      
      for (int j = 0; j < header.length; j++) {
        new_variant.information.put(header[j], pieces[j]);
      }
      data.addVariant(new_variant);
    }
  }
  
  return(data);
}




class Variant {
  String gene_ID;
  String change;
  float frequency;
  float odds_ratio;
  boolean freq_est;
  HashMap information;
  
  Variant(String passed_gene_ID, String passed_change) {
    gene_ID = passed_gene_ID;
    change = passed_change;
    
    // the rest is initialized to defaults
    frequency = 0; odds_ratio = -1; freq_est = false;
    information = new HashMap();
  }
}



class EvidenceData {
  HashMap variant_data;
  String[] variants_ID_list;
  HashMap columns;
  String[] columns_names_list;
  
  EvidenceData() {
    variant_data = new HashMap();
    variants_ID_list = new String[0];
    columns = new HashMap();
    columns_names_list = new String[0];
  }
  
  void addVariant (Variant new_variant) {
    String variant_ID = makeID(new_variant.gene_ID, new_variant.change);
    variant_data.put(variant_ID, new_variant);
    variants_ID_list = (String[]) append(variants_ID_list, variant_ID);
  }
  
  Variant getVariant (String variant_ID) {
    Variant retrieved_variant = (Variant) variant_data.get(variant_ID);
    return retrieved_variant;
  }
  
  String[] getVariantIDList () {
    return variants_ID_list;
  }
  
  void addColumn (String column_name, int column_number) {
    columns.put(column_name, column_number);
    columns_names_list = (String[]) append(columns_names_list, column_name);
  }
  
  int getColumn (String column_name) {
    if (columns.containsKey(column_name)) {
      int column = (Integer) columns.get(column_name);
      return column;
    } else {
      println("No column for label " + column_name);
      return 0;
    }
  }
  
  String makeID (String passed_gene_ID, String passed_change) {
    String new_ID = passed_gene_ID + "-" + passed_change;
    return new_ID;
  }
}
