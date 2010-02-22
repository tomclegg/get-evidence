

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

void setup() {
  
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
  value_x_min = -1.2;
  value_x_max = 2.7;
  value_y_min = -4.3;
  value_y_max = (log(0.5) / log(10));
  
  // Read in file
  data = ReadTable("http://evidence.personalgenomes.org/latest_vis_data.tsv");
  data_plot_positions = new HashMap();
  variant_ID_display = "";
  
  // set font
  plotFont = createFont("Arial", 16);
  textFont(plotFont);
  
  smooth();
}

void draw() {
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


void drawXLabels(float interval) {
  fill(0);
  textSize(12);
  textAlign(CENTER, TOP);
  strokeWeight(2);
  stroke(0);
  for (float log_x_value = int(value_x_min); log_x_value <= value_x_max; log_x_value += interval) {
    float x = map(log_x_value, value_x_min, value_x_max, plotX1, plotX2);
    float x_value = pow(10,log_x_value);
    String out = nf(x_value,0,0);
    //println(x_value + " " + log_x_value + " " + x + " " + out);
    text(out, x, plotY2 + 6);
    line(x,plotY2,x,plotY2+3);
  }
}

void drawYLabels(float interval) {
  fill(0);
  textSize(12);
  textAlign(RIGHT, CENTER);
  strokeWeight(2);
  stroke(0);
  for (float log_y_value = int(value_y_min); log_y_value <= value_y_max; log_y_value += interval) {
    float y = map(log_y_value, value_y_min, value_y_max, plotY2, plotY1);
    float y_value = pow(10,log_y_value);
    String out = nf(y_value,0,4);
    if (y_value >= 0.001) {
      out = nf(y_value,0,0);
    }
    text(out, plotX1 - 6, y);
    line(plotX1,y,plotX1-3,y);
  }
  
  // add 0.5 datapoint
  float y = map(log(0.5)/log(10), value_y_min, value_y_max, plotY2, plotY1);
  float y_value = 0.5;
  String out = nf(0.5,0,0);
  text(out, plotX1 - 6, y);
  line(plotX1,y,plotX1-3,y);
}

