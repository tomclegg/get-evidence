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
color dominant = color(255,0,0);
color recessive = color(0,0,255);
color inheritance_other = color(180,0,180);

// impact
color pathogenic = color(255,0,0);
color pathogenic_likely = color(255,80,20);
color pathogenic_uncertain = color(255,200,80);
color pharmacogenetic = color(200,0,200);
color pharmacogenetic_likely = color(200,30,200);
color pharmacogenetic_uncertain = color(200,60,200);
color benign = color(0,0,255);
color benign_likely = color(30,30,255);
color benign_uncertain = color(60,60,255);
color protective = color(0,180,120);
color protective_likely = color(0,180,120);
color protective_uncertain = color(0,180,120);
color color_unknown = color(120,120,120,150);

String data_color_mode = "impact";

void setup() {
  
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
  init_value_x_min = value_x_min = log(1.6);
  init_value_x_max = value_x_max = log(31);
  init_value_y_min = value_y_min = (log(0.0009) / log(10));
  init_value_y_max = value_y_max = (log(0.5) / log(10));
    
  // set font
  plotFont = createFont("Arial", 16);
  textFont(plotFont);
  
  // Read in file
  data = ReadTable("http://mad.printf.net/latest-flat_final.tsv"); //"http://evidence.personalgenomes.org/latest_vis_data.tsv");
  data_plot_positions = new HashMap();
  variant_ID_display = "";
  
  smooth();
}

void draw() {
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

void drawXLogLabels(float interval) {
  fill(0);
  textSize(12);
  textAlign(CENTER, TOP);
  strokeWeight(2);
  stroke(0);
  int start_x = int(exp(value_x_min) + 0.5);
  int end_x = int(exp(value_x_max) + 0.5);
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

void drawYLogLabels() {
  fill(0);
  textSize(12);
  textAlign(RIGHT, CENTER);
  strokeWeight(2);
  stroke(0);
  float y_min_value = pow(10,value_y_min);
  int power = 0;
  int start_y_value;
  while (int(pow(10,power)*y_min_value) <= 0) {
   power++;
   //println(int(pow(10,power) * y_min_value));
  }
  start_y_value = int(pow(10,power) * y_min_value);
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
    if (y_value >= 0.001) {
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

