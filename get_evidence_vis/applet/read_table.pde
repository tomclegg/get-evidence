// reads in tab-separated table
// first line of the file should be the column headers

EvidenceData ReadTable (String filename) {
  data = new EvidenceData();
  
  String[] rows = loadStrings(filename);
  
  // get headers and corresponding columns
  String[] header = split(rows[0], '\t');
  for (int i = 0; i < header.length; i++) {
    data.addColumn(header[i],i);
    println("Added column " + header[i]);
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
      println("ERROR: Less than entries in row unequal to the header, row number " + i + " should be " + header.length + " but is " + pieces.length);
    }
    
    // Get gene & change data
    String gene_ID = pieces[data.getColumn("gene")];
    String change = pieces[data.getColumn("aa_change")];
    
    // Get odds ratio
    String odds_ratio = pieces[data.getColumn("max_or_or")];
    if (odds_ratio.equals("-") || odds_ratio.equals("")) {
      odds_ratio = "NA / unknown";
    }
    
    // Get significance
    String significance_string = pieces[data.getColumn("significance")];
    float significance = 1.0;
    if ( pieces[data.getColumn("significance")].length() > 0 ) {
      significance = parseFloat( significance_string );
    }
    
    // Set frequency, if 0 estimate as 0.5 if missing try, or try to use OR data to estimate
    String frequency_string = pieces[data.getColumn("overall_frequency")];
    float frequency = -1;
    boolean is_frequency_estimated = false;
    if (frequency_string.length() > 0) {
      frequency = parseFloat(pieces[data.getColumn("overall_frequency")]);
    }
    
    // Create variant with relevant data
    // Currently only do this for variants with odds ratio and frequency information
    if (frequency > 0.5) {
       frequency = 1 - frequency;
       if (!(odds_ratio.equals("NA / unknown")) ) {
         float odds_ratio_number = parseFloat(odds_ratio);
         odds_ratio_number = 1 / odds_ratio_number;
         odds_ratio = nf(odds_ratio_number,0,2);
       }
     }
    if (frequency > pow(10,value_y_min)) {
      Variant new_variant = new Variant(gene_ID, change);
      new_variant.frequency = frequency;
      new_variant.odds_ratio = odds_ratio;
      new_variant.significance = significance;
      
      for (int j = 0; j < header.length; j++) {
        new_variant.information.put(header[j], pieces[j]);
      }
      data.addVariant(new_variant);
    } else {
      println("Can't use variant " + gene_ID + " " + change + " freq: " + frequency);
    }
  }
  
  return(data);
}


