
void drawData (EvidenceData data) {
  strokeWeight(4);
  String[] variant_IDs = data.getVariantIDList();
  
  for (int i = 0; i < variant_IDs.length; i++) {
    String variant_ID = variant_IDs[i];
    Variant current_variant = data.getVariant(variant_ID);
    float log_odds_ratio = log(current_variant.odds_ratio) / log(10);
    float log_frequency = log(current_variant.frequency) / log(10);
    float x_pos = map(log_odds_ratio,value_x_min,value_x_max,plotX1,plotX2);
    float y_pos = map(log_frequency,value_y_min,value_y_max,plotY2,plotY1);

    if (variant_ID.equals(variant_ID_display)) {
      stroke(0);
      strokeWeight(8);
      point(x_pos, y_pos);
      strokeWeight(6);
      stroke(255);
      point(x_pos, y_pos);
      stroke(0);
      strokeWeight(4);
    }
    
    if (data_color_mode.equals("inheritance")) {
      String inheritance = (String) current_variant.information.get("inheritance");
      if (inheritance.equals("dominant")) {
        stroke(255,0,0);
      } else {
        if (inheritance.equals("recessive")) {
          stroke(0,0,255);
        } else {
          stroke(100,100,100);
        }
      }
    } else if (data_color_mode.equals("impact")) {
      String impact = (String) current_variant.information.get("impact");
      if (impact.indexOf("pathogenic") >= 0) {
        stroke(255,0,0);
      } else {
        if (impact.indexOf("protective") >= 0) {
          stroke(0,0,255);
        } else {
          stroke(100,100,100);
        }
      }
    }
    
    point(x_pos, y_pos);
    float[] position = {x_pos, y_pos};
    data_plot_positions.put(variant_ID, position);
  }
}


void drawVariantInfo (Variant target_variant) {
  noFill();
  
  float curr_y = infoY1 + 10;
  float max_width = (infoX2 - infoX1) - (10);
  
  // print variant gene and change
  textSize(16);
  textAlign(LEFT,TOP);
  String title = target_variant.gene_ID + "-" + target_variant.change;
  text(title, infoX1 + 10, curr_y);
  curr_y += 32;
  
  // print disease name
  textSize(14);
  String disease_name = (String) target_variant.information.get("max_or_disease_name");
  if (textWidth(disease_name) > max_width) {
    String[] lines = splitStrings(disease_name, max_width);
    for (int i = 0; i < lines.length; i++) {
      text(lines[i], infoX1 + 10, curr_y);
      curr_y += 24;
    }
  } else {
    text(disease_name, infoX1 + 10, curr_y);
    curr_y += 48;
  }

  // print frequency  
  textSize(12);
  textAlign(RIGHT,TOP);
  if (target_variant.freq_est) {
    text("Frequency (est): ", infoX1 + 130, curr_y);
  } else {
    text("Frequency: ", infoX1 + 130, curr_y);
  }
  textAlign(LEFT,TOP);
  text( nf(target_variant.frequency,0,4), infoX1 + 135, curr_y);
  curr_y += 18;
  
  // print odds ratio
  textAlign(RIGHT,TOP);
  text("Odds ratio: ", infoX1 + 130, curr_y);
  textAlign(LEFT,TOP);
  text( nf(target_variant.odds_ratio,0,4), infoX1 + 135, curr_y);
  curr_y += 18;

  // print impact
  textAlign(RIGHT,TOP);
  text("Impact: ", infoX1 + 130, curr_y);
  textAlign(LEFT,TOP);
  text( (String) target_variant.information.get("impact"), infoX1 + 135, curr_y);
  curr_y += 18;
  
  // print inheritance
  textAlign(RIGHT,TOP);
  text("Inheritance: ", infoX1 + 130, curr_y);
  textAlign(LEFT,TOP);
  text( (String) target_variant.information.get("inheritance"), infoX1 + 135, curr_y);
  curr_y += 18;
  
  textSize(9);
  max_width = (infoX2 - infoX1) - (135 + 5);
  String[] data_columns_to_print = { "overall_frequency_n", "overall_frequency_d", "overall_frequency", "qualityscore_in_silico", "qualityscore_in_vitro", "qualityscore_case_control", "qualityscore_familial", "qualityscore_clinical", "max_or_case_pos", "max_or_case_neg", "max_or_control_pos", "max_or_control_neg" };
  for (int i = 0; i < data_columns_to_print.length; i++) {
    String target_variant_column_data = (String) target_variant.information.get(data_columns_to_print[i]);
    if (textWidth(target_variant_column_data) > max_width) {
      textAlign(RIGHT,TOP);
      String data_type = data_columns_to_print[i] + ": ";
      text(data_type, infoX1 + 130, curr_y);
      textAlign(LEFT, TOP);
      String[] data_lines = splitStrings(target_variant_column_data, max_width);
      for (int j = 0; j < data_lines.length; j++) {
        text(data_lines[j], infoX1 + 135, curr_y);
        curr_y += 12;
      }
    } else {
      textAlign(RIGHT,TOP);
      String data_type = data_columns_to_print[i] + ": ";
      text(data_type, infoX1 + 130, curr_y);
      textAlign(LEFT, TOP);
      text(target_variant_column_data, infoX1 + 135, curr_y);
      curr_y += 12;
    }
  }
}

String[] splitStrings (String unbroken, float max_width) {
  float text_width = textWidth(unbroken);
  String[] returned_strings = new String[0];
  
  if (text_width > max_width) {
    String[] words = split(unbroken, ' ');
    returned_strings = (String[]) append(returned_strings, words[0]);
    if (words.length > 1) {
      for (int i = 1; i < words.length; i++) {
        if (words[i].length() == 0) {
          continue;
        }
        String latest_line = returned_strings[returned_strings.length - 1];
        String new_line = latest_line + " " + words[i];
        if (textWidth(new_line) > max_width) {
          returned_strings = (String[]) append(returned_strings, words[i]);
        } else {
          returned_strings[returned_strings.length - 1] = new_line;
        }
      }
    }
  } else {
    returned_strings = (String[]) append(returned_strings, unbroken);
  }  
  
  return returned_strings;
}

void drawColorTypeButtons() {
  rect(infoX1,infoY2,infoX2 - infoX1,plotY2 - infoY2);
  textSize(10);
  
  // inheritance button
  fill(100);
  noStroke();
  rect(infoX1 + color_inheritance_x_rel - 0.5 * 60, infoY2 + color_button_y - 0.5 * color_button_size, 60, color_button_size);
  fill(255);
  textAlign(CENTER,CENTER);
  text("inheritance", infoX1 + color_inheritance_x_rel, infoY2 + color_button_y);
  
  // impact button
  fill(100);
  noStroke();
  rect(infoX1 + color_impact_x_rel - 0.5 * 60, infoY2 + color_button_y - 0.5 * color_button_size, 60, color_button_size);
  fill(255);
  textAlign(CENTER,CENTER);
  text("impact", infoX1 + color_impact_x_rel, infoY2 + color_button_y);
  
  fill(255,255,0);
  if (data_color_mode.equals("inheritance")) {
    text("inheritance", infoX1 + color_inheritance_x_rel, infoY2 + color_button_y);
  } else if (data_color_mode.equals("impact")) {
    text("impact", infoX1 + color_impact_x_rel, infoY2 + color_button_y);
  }
  
}

