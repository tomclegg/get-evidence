import processing.core.*; 
import processing.xml.*; 

import java.applet.*; 
import java.awt.*; 
import java.awt.image.*; 
import java.awt.event.*; 
import java.io.*; 
import java.net.*; 
import java.text.*; 
import java.util.*; 
import java.util.zip.*; 
import java.util.regex.*; 

public class get_evidence_vis extends PApplet {



EvidenceData data;
HashMap data_plot_positions;
String variant_ID_display;

PFont plotFont;

float plotX1, plotX2, plotY1, plotY2;
float infoX1, infoX2, infoY1, infoY2;
float value_x_min, value_x_max, value_y_min, value_y_max;
float color_button_size = 15;
float color_button_y = 20;
float color_inheritance_x_rel = 40;
float color_impact_x_rel = 120;

String data_color_mode = "inheritance";

public void setup() {
  
  // Set coordinates
  size(800, 500);
  plotX1 = 70;
  plotX2 = width - plotX1 - 300;
  plotY1 = 30;
  plotY2 = height - (plotY1 + 20);
  infoX1 = plotX2 + 50;
  infoX2 = width - 50;
  infoY1 = plotY1;
  infoY2 = plotY1 + 350;
  
  // Set X and Y values
  value_x_min = -1.2f;
  value_x_max = 2.7f;
  value_y_min = -4.3f;
  value_y_max = (log(0.5f) / log(10));
  
  // Read in file
  data = ReadTable("http://evidence.personalgenomes.org/latest_vis_data.tsv");
  data_plot_positions = new HashMap();
  variant_ID_display = "";
  
  // set font
  plotFont = createFont("Arial", 16);
  textFont(plotFont);
  
  smooth();
}

public void draw() {
  background(255);
  
  stroke(0);
  strokeWeight(2);
  noFill();
  //rectMode(CORNERS);
  line(plotX1,plotY1,plotX1,plotY2);
  line(plotX1,plotY2,plotX2,plotY2);
  
  drawYLabels(1);
  drawXLabels(1);
  
  drawData(data);
  
  // Draw titles
  textAlign(CENTER, TOP);
  textSize(16);
  String xtitle = "Odds ratio";
  text(xtitle, (plotX1 + plotX2) / 2, plotY2 + 20);
  
  textAlign(CENTER, BOTTOM);
  String ytitle = "Allele frequency";
  rotate(-PI/2);
  text(ytitle,-1 * (plotY1 + plotY2) / 2, plotX1 - 35);
  rotate(PI/2);
  
  stroke(220);
  noFill();
  rect(infoX1, infoY1, infoX2 - infoX1, infoY2 - infoY1);
  if (variant_ID_display.length() > 0) {
    drawVariantInfo(data.getVariant(variant_ID_display));
  }
  
  drawColorTypeButtons();
  
}


public void drawXLabels(float interval) {
  fill(0);
  textSize(12);
  textAlign(CENTER, TOP);
  strokeWeight(2);
  stroke(0);
  for (float log_x_value = PApplet.parseInt(value_x_min); log_x_value <= value_x_max; log_x_value += interval) {
    float x = map(log_x_value, value_x_min, value_x_max, plotX1, plotX2);
    float x_value = pow(10,log_x_value);
    String out = nf(x_value,0,0);
    //println(x_value + " " + log_x_value + " " + x + " " + out);
    text(out, x, plotY2 + 6);
    line(x,plotY2,x,plotY2+3);
  }
}

public void drawYLabels(float interval) {
  fill(0);
  textSize(12);
  textAlign(RIGHT, CENTER);
  strokeWeight(2);
  stroke(0);
  for (float log_y_value = PApplet.parseInt(value_y_min); log_y_value <= value_y_max; log_y_value += interval) {
    float y = map(log_y_value, value_y_min, value_y_max, plotY2, plotY1);
    float y_value = pow(10,log_y_value);
    String out = nf(y_value,0,4);
    if (y_value >= 0.001f) {
      out = nf(y_value,0,0);
    }
    text(out, plotX1 - 6, y);
    line(plotX1,y,plotX1-3,y);
  }
  
  // add 0.5 datapoint
  float y = map(log(0.5f)/log(10), value_y_min, value_y_max, plotY2, plotY1);
  float y_value = 0.5f;
  String out = nf(0.5f,0,0);
  text(out, plotX1 - 6, y);
  line(plotX1,y,plotX1-3,y);
}


public void drawData (EvidenceData data) {
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


public void drawVariantInfo (Variant target_variant) {
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

public String[] splitStrings (String unbroken, float max_width) {
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

public void drawColorTypeButtons() {
  rect(infoX1,infoY2,infoX2 - infoX1,plotY2 - infoY2);
  textSize(10);
  
  // inheritance button
  fill(100);
  noStroke();
  rect(infoX1 + color_inheritance_x_rel - 0.5f * 60, infoY2 + color_button_y - 0.5f * color_button_size, 60, color_button_size);
  fill(255);
  textAlign(CENTER,CENTER);
  text("inheritance", infoX1 + color_inheritance_x_rel, infoY2 + color_button_y);
  
  // impact button
  fill(100);
  noStroke();
  rect(infoX1 + color_impact_x_rel - 0.5f * 60, infoY2 + color_button_y - 0.5f * color_button_size, 60, color_button_size);
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

public void mousePressed() {
  float minDist = 10;
  
  String[] variant_IDs = data.getVariantIDList();
  
  if (variant_ID_display.length() > 0 && mouseButton == RIGHT) {
    if (mouseX >= infoX1 && mouseX <= infoX2 && mouseY >= infoY1 && mouseY <= infoY2) {
      String url = "http://evidence.personalgenomes.org/" + variant_ID_display;
      link(url, "_new");
    }
  } else if (abs(mouseX - (infoX1 + color_inheritance_x_rel)) < 30 && abs(mouseY - (infoY2 + color_button_y)) < (color_button_size / 2)) {
    data_color_mode = "inheritance";
  } else if (abs(mouseX - (infoX1 + color_impact_x_rel)) < 30 && abs(mouseY - (infoY2 + color_button_y)) < (color_button_size / 2)) {
    data_color_mode = "impact";
  } else {
    float closest_dist = minDist;
    String found_ID = "";
    for (int i = 0; i < variant_IDs.length; i++) {
      float[] position = (float[]) data_plot_positions.get(variant_IDs[i]);
      float distance = pow(pow(mouseX - position[0],2) + pow(mouseY - position[1],2), 0.5f);
      if (distance < closest_dist) {
        found_ID = variant_IDs[i];
        closest_dist = distance;
      }
    }
    variant_ID_display = found_ID;
  }
  
  //println(found_ID);
}
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


public EvidenceData ReadTable (String filename) {
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
            frequency = 0.5f / total_observations;
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
          frequency = control_pos * 1.0f / (control_pos + control_neg);
          is_frequency_estimated = true;
          //println("Calculated frequency from controls: " + frequency);
        } else {
          frequency = 0.5f / (control_pos + control_neg);
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
      if (frequency > 0.5f) {
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
  
  public void addVariant (Variant new_variant) {
    String variant_ID = makeID(new_variant.gene_ID, new_variant.change);
    variant_data.put(variant_ID, new_variant);
    variants_ID_list = (String[]) append(variants_ID_list, variant_ID);
  }
  
  public Variant getVariant (String variant_ID) {
    Variant retrieved_variant = (Variant) variant_data.get(variant_ID);
    return retrieved_variant;
  }
  
  public String[] getVariantIDList () {
    return variants_ID_list;
  }
  
  public void addColumn (String column_name, int column_number) {
    columns.put(column_name, column_number);
    columns_names_list = (String[]) append(columns_names_list, column_name);
  }
  
  public int getColumn (String column_name) {
    if (columns.containsKey(column_name)) {
      int column = (Integer) columns.get(column_name);
      return column;
    } else {
      println("No column for label " + column_name);
      return 0;
    }
  }
  
  public String makeID (String passed_gene_ID, String passed_change) {
    String new_ID = passed_gene_ID + "-" + passed_change;
    return new_ID;
  }
}

  static public void main(String args[]) {
    PApplet.main(new String[] { "--bgcolor=#ffffff", "get_evidence_vis" });
  }
}
