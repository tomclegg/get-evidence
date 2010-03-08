
void drawData (EvidenceData data) {
  strokeWeight(4);
  String[] variant_IDs = data.getVariantIDList();
  
  for (int i = 0; i < variant_IDs.length; i++) {
    // get variant
    String variant_ID = variant_IDs[i];
    Variant current_variant = data.getVariant(variant_ID);
    
    // Calculate position
    int stars = countStars(current_variant);
    float log_frequency = log(current_variant.frequency) / log(10);
    float x_pos = map(log(1 + stars + random(-0.3,0.3)),value_x_min,value_x_max,plotX1,plotX2);
    float y_pos = map(log_frequency,value_y_min,value_y_max,plotY2,plotY1);

    // dot color & dot size get set according to coloring scheme
    color dot_color = color_unknown;
    float dot_size = 2;
    
    if (data_color_mode.equals("inheritance")) {
      String inheritance = (String) current_variant.information.get("inheritance");
      if (inheritance.equals("dominant")) {
        dot_color = dominant;
        dot_size = 4;
      } else if (inheritance.equals("recessive")) {
        dot_color = recessive;
        dot_size = 4;
      } else if (inheritance.equals("other")) {
        dot_color = inheritance_other;
        dot_size = 4;
      }
    } else if (data_color_mode.equals("impact")) {
      String impact = (String) current_variant.information.get("impact");
      if (impact.equals("pathogenic")) {
        String certainty = (String) current_variant.information.get("certainty");
        if (certainty.equals("2")) {
          dot_color = pathogenic;
          dot_size = 6;
        } else if (certainty.equals("1")) {
          dot_color = pathogenic_likely;
          dot_size = 5;
        } else {
          dot_color = pathogenic_uncertain;
          dot_size = 4;
        }
      } else if (impact.equals("pharmacogenetic")) {
        String certainty = (String) current_variant.information.get("certainty");
        if (certainty.equals("2")) {
          dot_color = pharmacogenetic;
          dot_size = 6;
        } else if (certainty.equals("1")) {
          dot_color = pharmacogenetic_likely;
          dot_size = 5;
        } else {
          dot_color = pharmacogenetic_uncertain;
          dot_size = 4;
        }
      } else if (impact.equals("benign")) {
        String certainty = (String) current_variant.information.get("certainty");
        if (certainty.equals("2")) {
          dot_color = benign;
          dot_size = 6;
        } else if (certainty.equals("1")) {
          dot_color = benign_likely;
          dot_size = 5;
        } else {
          dot_color = benign_uncertain;
          dot_size = 4;
        }
      } else if (impact.equals("protective")) {
        String certainty = (String) current_variant.information.get("impact");
        if (certainty.equals("2")) {
          dot_color = protective;
          dot_size = 6;
        } else if (certainty.equals("1")) {
          dot_color = protective_likely;
          dot_size = 5;
        } else {
          dot_color = protective_uncertain;
          dot_size = 4;
        }
      }
    }
    
    if (variant_ID.equals(variant_ID_display)) {
      stroke(0);
      strokeWeight(dot_size + 4);
      point(x_pos, y_pos);
      strokeWeight(dot_size + 2);
      stroke(255);
      point(x_pos, y_pos);
      stroke(0);
      strokeWeight(dot_size);
    }

    if (x_pos >= plotX1 && x_pos <= plotX2 && y_pos >= plotY1 && y_pos <= plotY2) {
      stroke(dot_color);
      strokeWeight(dot_size);
      point(x_pos, y_pos);
      float[] position = {x_pos, y_pos};
      data_plot_positions.put(variant_ID, position);
    } else {
      float[] position = {-100, -100};
      data_plot_positions.put(variant_ID, position);
    }
  }
}


void drawVariantInfo (Variant target_variant) {
  // draw open-page button
  fill(100);
  noStroke();
  textSize(10);
  textAlign(LEFT,CENTER);
  rect(infoX2 - 10, infoY1 + 10, -60, color_button_size);
  fill(255);
  text("open page", infoX2 - 65, infoY1 + 8 + (color_button_size / 2));
  
  fill(0);
  float curr_y = infoY1 + 10;
  float max_width = (infoX2 - infoX1) - (15);
  
  // print variant gene and change
  textSize(16);
  textAlign(LEFT,TOP);
  String title = target_variant.gene_ID + "-" + target_variant.change;
  text(title, infoX1 + 10, curr_y);
  curr_y += 24;
  
  // print disease name
  textSize(14);
  String disease_name = (String) target_variant.information.get("max_or_disease_name");
  if (textWidth(disease_name) > max_width) {
    String[] lines = splitStrings(disease_name, max_width);
    for (int i = 0; i < lines.length; i++) {
      text(lines[i], infoX1 + 10, curr_y);
      curr_y += 21;
    }
  } else {
    text(disease_name, infoX1 + 10, curr_y);
    curr_y += 21;
  }

  // print disease description
  textSize(10);
  String disease_description = (String) target_variant.information.get("summary_short");
  String[] lines = splitStrings(disease_description, max_width);
  for (int i = 0; i < lines.length; i++) {
    text(lines[i], infoX1 + 10, curr_y);
    curr_y += 12;
  }
  curr_y += 4;

  // print impact
  textAlign(RIGHT,TOP);
  text("Impact: ", infoX1 + 130, curr_y);
  textAlign(LEFT,TOP);
  String impact_text = (String) target_variant.information.get("impact");
  if (target_variant.information.get("certainty").equals("1")) {
    impact_text = "likely " + impact_text;
  } else if (target_variant.information.get("certainty").equals("0")) {
    impact_text = "uncertain " + impact_text;
  }
  text( impact_text, infoX1 + 135, curr_y);
  curr_y += 12;
  
  // print inheritance
  textAlign(RIGHT,TOP);
  text("Inheritance: ", infoX1 + 130, curr_y);
  textAlign(LEFT,TOP);
  text( (String) target_variant.information.get("inheritance"), infoX1 + 135, curr_y);
  curr_y += 12;

  // print frequency  
  textSize(10);
  textAlign(RIGHT,TOP);
  text("Frequency: ", infoX1 + 130, curr_y);
  textAlign(LEFT,TOP);
  text( nf(target_variant.frequency,0,4), infoX1 + 135, curr_y);
  curr_y += 12;
  
  // print odds ratio
  textAlign(RIGHT,TOP);
  text("Odds ratio: ", infoX1 + 130, curr_y);
  textAlign(LEFT,TOP);
  text( target_variant.odds_ratio, infoX1 + 135, curr_y);
  curr_y += 12;
  
  // print significance
  textAlign(RIGHT,TOP);
  text("Significance: ", infoX1 + 130, curr_y);
  textAlign(LEFT,TOP);
  if (target_variant.significance < 0.99) {
    text( nf(target_variant.significance,0,8), infoX1 + 135, curr_y);
  } else {
    text( "unknown", infoX1 + 135, curr_y);
  }
  curr_y += 12;
  
  textSize(10);
  max_width = (infoX2 - infoX1) - (135 + 5);
  
  // print "In OMIM"
  textAlign(RIGHT, TOP);
  text("In OMIM: ", infoX1 + 130, curr_y);
  textAlign(LEFT, TOP);
  if (target_variant.information.get("in_omim").equals("Y")) {
    text("Yes", infoX1 + 135, curr_y);
  } else {
    text("No", infoX1 + 135, curr_y);
  }
  curr_y += 12;
  
  // print quality scores
  textAlign(RIGHT,TOP);
  text("Computational evidence: ", infoX1 + 130, curr_y);
  textAlign(LEFT,TOP);
  text((String) target_variant.information.get("qualityscore_in_silico"), infoX1 + 135, curr_y);
  curr_y += 12;
  textAlign(RIGHT,TOP);
  text("Experimental evidence: ", infoX1 + 130, curr_y);
  textAlign(LEFT,TOP);
  text((String) target_variant.information.get("qualityscore_in_vitro"), infoX1 + 135, curr_y);
  curr_y += 12;
  textAlign(RIGHT,TOP);
  text("Case/control evidence: ", infoX1 + 130, curr_y);
  textAlign(LEFT,TOP);
  text((String) target_variant.information.get("qualityscore_case_control"), infoX1 + 135, curr_y);
  curr_y += 12;
  textAlign(RIGHT,TOP);
  text("Familial evidence: ", infoX1 + 130, curr_y);
  textAlign(LEFT,TOP);
  text((String) target_variant.information.get("qualityscore_familial"), infoX1 + 135, curr_y);
  curr_y += 12;
  textAlign(RIGHT,TOP);
  text("Severity: ", infoX1 + 130, curr_y);
  textAlign(LEFT,TOP);
  text((String) target_variant.information.get("qualityscore_severity"), infoX1 + 135, curr_y);
  curr_y += 12;
  textAlign(RIGHT,TOP);
  text("Treatability: ", infoX1 + 130, curr_y);
  textAlign(LEFT,TOP);
  text((String) target_variant.information.get("qualityscore_treatability"), infoX1 + 135, curr_y);
  curr_y += 12;

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
  noFill();
  stroke(220);
  rect(infoX1,infoY2,infoX2 - infoX1,plotY2 - infoY2);
  
  // inheritance button
  fill(100);
  noStroke();
  rect(infoX1 + color_inheritance_x_rel - 0.5 * 60, infoY2 + color_button_y - 0.5 * color_button_size, 60, color_button_size);
  rect(infoX1 + color_impact_x_rel - 0.5 * 60, infoY2 + color_button_y - 0.5 * color_button_size, 60, color_button_size);

  textSize(10);  
  textAlign(CENTER,CENTER);
  if (data_color_mode.equals("inheritance")) {
    fill(255,255,0);
  } else {
    fill(255,255,255);
  }
  text("inheritance", infoX1 + color_inheritance_x_rel, infoY2 + color_button_y);
  if (data_color_mode.equals("impact")) {
    fill(255,255,0);
  } else {
    fill(255,255,255);
  }
  text("impact", infoX1 + color_impact_x_rel, infoY2 + color_button_y);
  
}


int countStars (Variant target_variant) {
  int total_score = 0;
  String in_silico = (String) target_variant.information.get("qualityscore_in_silico");
  if ( ! (in_silico.equals("-")) ) {
    int score = parseInt(in_silico);
    total_score = total_score + score;
  } 
  String in_vitro = (String) target_variant.information.get("qualityscore_in_vitro");
  if ( ! (in_vitro.equals("-")) ) {
    int score = parseInt(in_vitro);
    total_score = total_score + score;
  }
  String case_control = (String) target_variant.information.get("qualityscore_case_control");
  if ( ! (case_control.equals("-")) ) {
    int score = parseInt(case_control);
    total_score = total_score + score;
  }
  String familial = (String) target_variant.information.get("qualityscore_familial");
  if ( ! (familial.equals("-")) ) {
    int score = parseInt(familial);
    total_score = total_score + score;
  }
  String severity = (String) target_variant.information.get("qualityscore_severity");
  if ( ! (severity.equals("-")) ) {
    int score = parseInt(severity);
    total_score = total_score + score;
  }
  String treatability = (String) target_variant.information.get("qualityscore_treatability");
  if ( ! (treatability.equals("-")) ) {
    int score = parseInt(treatability);
    total_score = total_score + score;
  }

  
  
  return(total_score);
}

void drawKey() {
  fill(0);
  textSize(10);
  textAlign(LEFT,CENTER);
  if (data_color_mode.equals("impact")) {
    stroke(pathogenic);
    strokeWeight(6);
    point(infoX1 + 15, keyY1 + 20);
    text("= pathogenic", infoX1 + 25, keyY1 + 20);
    stroke(pathogenic_likely);
    strokeWeight(5);
    point(infoX1 + 15, keyY1 + 32);
    text("= likely pathogenic", infoX1 + 25, keyY1 + 32);
    stroke(pathogenic_uncertain);
    strokeWeight(4);
    point(infoX1 + 15, keyY1 + 44);
    text("= uncertain pathogenic", infoX1 + 25, keyY1 + 44);
    stroke(benign);
    strokeWeight(6);
    point(infoX1 + 15, keyY1 + 56);
    text("= benign", infoX1 + 25, keyY1 + 56);
    stroke(benign_likely);
    strokeWeight(5);
    point(infoX1 + 15, keyY1 + 68);
    text("= likely benign", infoX1 + 25, keyY1 + 68);
    stroke(benign_uncertain);
    strokeWeight(4);
    point(infoX1 + 15, keyY1 + 80);
    text("= uncertain benign", infoX1 + 25, keyY1 + 80);
    stroke(color_unknown);
    strokeWeight(2);
    point(infoX1 + 15, keyY1 + 92);
    text("= unknown / unannotated / other", infoX1 + 25, keyY1 + 92);
    
    stroke(pharmacogenetic);
    strokeWeight(6);
    point(infoX1 + 140, keyY1 + 20);
    text("= pharmacogenetic", infoX1 + 150, keyY1 + 20);
    stroke(pharmacogenetic_likely);
    strokeWeight(5);
    point(infoX1 + 140, keyY1 + 32);
    text("= likely pharmacogenetic", infoX1 + 150, keyY1 + 32);
    stroke(pharmacogenetic_uncertain);
    strokeWeight(4);
    point(infoX1 + 140, keyY1 + 44);
    text("= uncertain pharmacogenetic", infoX1 + 150, keyY1 + 44);
    stroke(protective);
    strokeWeight(6);
    point(infoX1 + 140, keyY1 + 56);
    text("= protective", infoX1 + 150, keyY1 + 56);
    stroke(protective_likely);
    strokeWeight(5);
    point(infoX1 + 140, keyY1 + 68);
    text("= likely protective", infoX1 + 150, keyY1 + 68);
    stroke(protective_uncertain);
    strokeWeight(4);
    point(infoX1 + 140, keyY1 + 80);
    text("= uncertain protective", infoX1 + 150, keyY1 + 80);
  } else if (data_color_mode.equals("inheritance")) {
    stroke(dominant);
    strokeWeight(4);
    point(infoX1 + 15, keyY1 + 20);
    text("= dominant", infoX1 + 25, keyY1 + 20);
    stroke(recessive);
    strokeWeight(4);
    point(infoX1 + 15, keyY1 + 32);
    text("= recessive", infoX1 + 25, keyY1 + 32);
    stroke(inheritance_other);
    strokeWeight(4);
    point(infoX1 + 15, keyY1 + 44);
    text("= other", infoX1 + 25, keyY1 + 44);
    stroke(color_unknown);
    strokeWeight(4);
    point(infoX1 + 15, keyY1 + 56);
    text("= unknown / not reported", infoX1 + 25, keyY1 + 56);
  } else if (data_color_mode.equals("inheritance")) {
    stroke(dominant);
    strokeWeight(4);
    point(infoX1 + 15, keyY1 + 20);
    text("= dominant", infoX1 + 25, keyY1 + 20);
    stroke(recessive);
    strokeWeight(4);
    point(infoX1 + 15, keyY1 + 32);
    text("= recessive", infoX1 + 25, keyY1 + 32);
    stroke(inheritance_other);
    strokeWeight(4);
    point(infoX1 + 15, keyY1 + 44);
    text("= other", infoX1 + 25, keyY1 + 44);
    stroke(color_unknown);
    strokeWeight(4);
    point(infoX1 + 15, keyY1 + 56);
    text("= unknown / not reported", infoX1 + 25, keyY1 + 56);
  }
}

void drawInstructionInfo() {
  fill(80,80,80);
  textSize(14);
  textAlign(TOP, LEFT);
  text("How to use this interactive graph:",infoX1 + 15, infoY1 + 40);
  textSize(11);
  text("Click on a point to highlight it & show its information", infoX1 + 15, infoY1 + 70);
  text("(its data will replace of this instruction panel)", infoX1 + 35, infoY1 + 85);
  
  text("Click elsewhere on the graph to remove the highlight", infoX1 + 15, infoY1 + 105);
  text("(and bring back these instructions)", infoX1 + 35, infoY1 + 120);
  
  text("Right-click (ctrl-click on Macs) on the graph to zoom in", infoX1 + 15, infoY1 + 140);

  text("Hit spacebar to zoom out", infoX1 + 15, infoY1 + 160);
  
  text("Open the webpage for a variant by clicking the", infoX1 + 15, infoY1 + 180);
  text("\"open page\" button next to the variant's information", infoX1 + 35, infoY1 + 195);
  text("(it will be on the upper right of this box)", infoX1 + 35, infoY1 + 210);
  
  text("Click on the buttons below to change the", infoX1 + 15, infoY1 + 230);
  text("coloring scheme of the data", infoX1 + 35, infoY1 + 245);
}
