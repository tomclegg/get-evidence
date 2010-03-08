class Variant {
  String gene_ID;
  String change;
  float frequency;
  String odds_ratio;
  HashMap information;
  float significance;
  
  Variant(String passed_gene_ID, String passed_change) {
    gene_ID = passed_gene_ID;
    change = passed_change;
    
    // the rest is initialized to defaults
    frequency = 0; odds_ratio = "NA";
    information = new HashMap(); significance = 1;
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
      //println("No column for label " + column_name);
      return 0;
    }
  }
  
  String makeID (String passed_gene_ID, String passed_change) {
    String new_ID = passed_gene_ID + "-" + passed_change;
    return new_ID;
  }
}

