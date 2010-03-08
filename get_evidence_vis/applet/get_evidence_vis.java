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

// plot, infobox, and key coordinates
float plotX1, plotX2, plotY1, plotY2;
float infoX1, infoX2, infoY1, infoY2;
float keyY1;

// x and y min and max, and initial values (zoom out does not go beyond these)
float value_x_min, value_x_max, value_y_min, value_y_max;
float init_value_x_min, init_value_x_max, init_value_y_min, init_value_y_max;

// Buttons to change coloring systems
float color_button_size = 15;
float color_button_y = 20;
float color_impact_x_rel = 40; float color_inheritance_x_rel = 120; 

// inheritance
int dominant = color(255,0,0);
int recessive = color(0,0,255);
int inheritance_other = color(180,0,180);

// impact
int pathogenic = color(255,0,0);
int pathogenic_likely = color(255,80,20);
int pathogenic_uncertain = color(255,200,80);
int pharmacogenetic = color(200,0,200);
int pharmacogenetic_likely = color(200,30,200);
int pharmacogenetic_uncertain = color(200,60,200);
int benign = color(0,0,255);
int benign_likely = color(30,30,255);
int benign_uncertain = color(60,60,255);
int protective = color(0,180,120);
int protective_likely = color(0,180,120);
int protective_uncertain = color(0,180,120);
int color_unknown = color(120,120,120,150);

String data_color_mode = "impact";

public void setup() {
  
  // Set coordinates
  size(900, 550);
  plotX1 = 70;
  plotX2 = width - plotX1 - 300;
  plotY1 = 30;
  plotY2 = height - (plotY1 + 20);
  infoX1 = plotX2 + 50;
  infoX2 = width - 30;
  infoY1 = plotY1;
  infoY2 = plotY1 + 335;
  keyY1 = infoY2 + 25;
  
  // Set X and Y values
  init_value_x_min = value_x_min = log(1.6f);
  init_value_x_max = value_x_max = log(31);
  init_value_y_min = value_y_min = (log(0.0009f) / log(10));
  init_value_y_max = value_y_max = (log(0.5f) / log(10));
    
  // set font
  plotFont = createFont("Arial", 16);
  textFont(plotFont);
  
  // Read in file
  data = ReadTable("http://mad.printf.net/latest-flat_final.tsv"); //"http://evidence.personalgenomes.org/latest_vis_data.tsv");
  data_plot_positions = new HashMap();
  variant_ID_display = "";
  
  smooth();
}

public void draw() {
  background(255);
  randomSeed(100);  // This is for noise added to X coordinates when plotting
  
  stroke(0);
  strokeWeight(2);
  noFill();
  line(plotX1,plotY1,plotX1,plotY2);
  line(plotX1,plotY2,plotX2,plotY2);
  
  drawYLogLabels();
  drawXLogLabels(1);
  
  drawData(data);
  
  // Draw titles
  textAlign(CENTER, TOP);
  textSize(16);
  String xtitle = "Variant quality score";
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
  } else {
    drawInstructionInfo();
  }
  drawKey();
  
  drawColorTypeButtons();

}

public void drawXLogLabels(float interval) {
  fill(0);
  textSize(12);
  textAlign(CENTER, TOP);
  strokeWeight(2);
  stroke(0);
  int start_x = PApplet.parseInt(exp(value_x_min) + 0.5f);
  int end_x = PApplet.parseInt(exp(value_x_max) + 0.5f);
  if (log(start_x) < value_x_min) {
    start_x++;
  }
  if (log(end_x) > value_x_max) {
    end_x--;
  }
  
  for (float x_value = start_x; x_value <= end_x; x_value += interval) {
    float log_x_value = log(x_value);
    float x = map(log_x_value, value_x_min, value_x_max, plotX1, plotX2);
    float true_x_value = x_value - 1;
    String out = nf(true_x_value,0,0);
    //println(x_value + " " + log_x_value + " " + x + " " + out);
    text(out, x, plotY2 + 6);
    line(x,plotY2,x,plotY2+3);
    
    float next_x = map(log(x_value+interval),value_x_min,value_x_max,plotX1,plotX2);
    while (abs(next_x - x) < 24) {
      interval++;
      next_x = map(log(x_value+interval),value_x_min,value_x_max,plotX1,plotX2);
    }
  }
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

public void drawYLogLabels() {
  fill(0);
  textSize(12);
  textAlign(RIGHT, CENTER);
  strokeWeight(2);
  stroke(0);
  float y_min_value = pow(10,value_y_min);
  int power = 0;
  int start_y_value;
  while (PApplet.parseInt(pow(10,power)*y_min_value) <= 0) {
   power++;
   //println(int(pow(10,power) * y_min_value));
  }
  start_y_value = PApplet.parseInt(pow(10,power) * y_min_value);
  //println("Done");
  for (int i = start_y_value; (i) * pow(10,(-1*power)) <= pow(10,value_y_max); i++) {
    if (i >= 10) {
      i = 1;
      power--;
    }
    float y_value = i * pow(10,(-1*power));
    float log_y_value = log(y_value) / log(10);
    float y = map(log_y_value, value_y_min, value_y_max, plotY2, plotY1);
    String out = nf(y_value,0,4);
    if (y_value >= 0.001f) {
      out = nf(y_value,0,0);
    }
    
    float next_major_y = map(-1 * (power - 1), value_y_min, value_y_max, plotY2, plotY1);
    if (next_major_y < plotY1 && (abs(plotY1 - y) > 1)) {
        next_major_y = plotY1;
    }
    if ( abs(next_major_y - y) >= 15) {
      if ( (log_y_value > value_y_min) ) {
        text(out, plotX1 - 6, y);
        line(plotX1,y,plotX1-3,y);
        
        float next_y_value = (i + 1) * pow(10,(-1 * power));
        float log_next_y_value = log(next_y_value) / log(10);
        float next_y = map(log_next_y_value, value_y_min, value_y_max, plotY2, plotY1);
        while (abs(next_y - y) < 15) {
          i++;
          if (i >= 9) {
            break;
          }
          next_y_value = (i + 1) * pow(10,(-1 * power));
          log_next_y_value = log(next_y_value) / log(10);
          next_y = map(log_next_y_value, value_y_min, value_y_max, plotY2, plotY1);    
        }
      }
    }
  }
}

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
      //println("No column for label " + column_name);
      return 0;
    }
  }
  
  public String makeID (String passed_gene_ID, String passed_change) {
    String new_ID = passed_gene_ID + "-" + passed_change;
    return new_ID;
  }
}


public void drawData (EvidenceData data) {
  strokeWeight(4);
  String[] variant_IDs = data.getVariantIDList();
  
  for (int i = 0; i < variant_IDs.length; i++) {
    // get variant
    String variant_ID = variant_IDs[i];
    Variant current_variant = data.getVariant(variant_ID);
    
    // Calculate position
    int stars = countStars(current_variant);
    float log_frequency = log(current_variant.frequency) / log(10);
    float x_pos = map(log(1 + stars + random(-0.3f,0.3f)),value_x_min,value_x_max,plotX1,plotX2);
    float y_pos = map(log_frequency,value_y_min,value_y_max,plotY2,plotY1);

    // dot color & dot size get set according to coloring scheme
    int dot_color = color_unknown;
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


public void drawVariantInfo (Variant target_variant) {
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
  if (target_variant.significance < 0.99f) {
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
  noFill();
  stroke(220);
  rect(infoX1,infoY2,infoX2 - infoX1,plotY2 - infoY2);
  
  // inheritance button
  fill(100);
  noStroke();
  rect(infoX1 + color_inheritance_x_rel - 0.5f * 60, infoY2 + color_button_y - 0.5f * color_button_size, 60, color_button_size);
  rect(infoX1 + color_impact_x_rel - 0.5f * 60, infoY2 + color_button_y - 0.5f * color_button_size, 60, color_button_size);

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


public int countStars (Variant target_variant) {
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

public void drawKey() {
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

public void drawInstructionInfo() {
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
public void mousePressed() {
  float minDist = 10;
  
  String[] variant_IDs = data.getVariantIDList();
  
  if (mouseButton == RIGHT) {
    if ( mouseX >= plotX1 && mouseX <= plotX2 && mouseY <= plotY2 && mouseY >= plotY1 ) {
      zoomIn( mouseX, mouseY );
    }
  } else if ( mouseX >= (infoX2 - 70) && mouseX <= (infoX2 - 10) && mouseY >= infoY1 + 10 && mouseY <= infoY1 + 10 + color_button_size ) {
     String url = "http://evidence.personalgenomes.org/" + variant_ID_display;
     link(url, "_new");    
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

public void keyPressed() {
  if (key == ' ') {
    zoomOut();
  }
}


public void zoomIn (float x_pos, float y_pos) {

  float magnification = 3.0f;
  
  float x_value = map(x_pos, plotX1, plotX2,value_x_min,value_x_max);
  float y_value = map(y_pos,plotY2, plotY1,value_y_min,value_y_max);
  
  float new_x_min, new_x_max, new_y_min, new_y_max;
  
  float current_x_span = value_x_max - value_x_min;
  if (x_value - (current_x_span / (2 * magnification)) < value_x_min) {
    new_x_min = value_x_min;
    new_x_max = value_x_min + (current_x_span / (2 * magnification));
  } else if (x_value + (current_x_span / (2 * magnification)) > value_x_max) {
    new_x_min = value_x_max - (current_x_span / (2 * magnification));
    new_x_max = value_x_max;
  } else {
    new_x_min = x_value - (current_x_span / (2 * magnification));
    new_x_max = x_value + (current_x_span / (2 * magnification));
  }
  
  float current_y_span = value_y_max - value_y_min;
  if (y_value - (current_y_span / (2 * magnification)) < value_y_min) {
    new_y_min = value_y_min;
    new_y_max = value_y_min + (current_y_span / (2 * magnification));
  } else if (y_value + (current_y_span / (2 * magnification)) > value_y_max) {
    new_y_min = value_y_max - (current_y_span / (2 * magnification));
    new_y_max = value_y_max;
  } else {
    new_y_min = y_value - (current_y_span / (2 * magnification));
    new_y_max = y_value + (current_y_span / (2 * magnification));
  }
  
  value_x_min = new_x_min;
  value_x_max = new_x_max;
  value_y_min = new_y_min;
  value_y_max = new_y_max;
  
}

public void zoomOut () {
  float magnification = 3.0f;
  float new_x_min, new_x_max, new_y_min, new_y_max;
  
  float plot_center_x = (value_x_min + value_x_max) / 2;
  float current_x_span = value_x_max - value_x_min;
  if (plot_center_x - (magnification * current_x_span) / 2 < init_value_x_min) {
    new_x_min = init_value_x_min;
    if (init_value_x_min + magnification * current_x_span > init_value_x_max) {
      new_x_max = init_value_x_max;
    } else {
      new_x_max = init_value_x_min + magnification * current_x_span;
    }
  } else if (plot_center_x + (magnification * current_x_span) / 2 > init_value_x_max) {
    new_x_max = init_value_x_max;
    if (init_value_x_max - magnification * current_x_span < init_value_x_min) {
      new_x_min = init_value_x_min;
    } else {
      new_x_min = init_value_x_max - magnification * current_x_span;
    }
  } else {
    new_x_min = plot_center_x - (magnification * current_x_span) / 2;
    new_x_max = plot_center_x + (magnification * current_x_span) / 2;
  }
  
  float plot_center_y = (value_y_min + value_y_max) / 2;
  float current_y_span = value_y_max - value_y_min;
  if (plot_center_y - (magnification * current_y_span) / 2 < init_value_y_min) {
    new_y_min = init_value_y_min;
    if (init_value_y_min + magnification * current_y_span > init_value_y_max) {
      new_y_max = init_value_y_max;
    } else {
      new_y_max = init_value_y_min + magnification * current_y_span;
    }
  } else if (plot_center_y + (magnification * current_y_span) / 2 > init_value_y_max) {
    new_y_max = init_value_y_max;
    if (init_value_y_max - magnification * current_y_span < init_value_y_min) {
      new_y_min = init_value_y_min;
    } else {
      new_y_min = init_value_y_max - magnification * current_y_span;
    }
  } else {
    new_y_min = plot_center_y - (magnification * current_y_span) / 2;
    new_y_max = plot_center_y + (magnification * current_y_span) / 2;
  }
  
  value_x_min = new_x_min;
  value_x_max = new_x_max;
  value_y_min = new_y_min;
  value_y_max = new_y_max;
  
}
// reads in tab-separated table
// first line of the file should be the column headers

public EvidenceData ReadTable (String filename) {
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
    float significance = 1.0f;
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
    if (frequency > 0.5f) {
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



  static public void main(String args[]) {
    PApplet.main(new String[] { "--bgcolor=#ffffff", "get_evidence_vis" });
  }
}
