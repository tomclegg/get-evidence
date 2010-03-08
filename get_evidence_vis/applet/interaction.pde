void mousePressed() {
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
      float distance = pow(pow(mouseX - position[0],2) + pow(mouseY - position[1],2), 0.5);
      if (distance < closest_dist) {
        found_ID = variant_IDs[i];
        closest_dist = distance;
      }
    }
    variant_ID_display = found_ID;
  }
  
  //println(found_ID);
}

void keyPressed() {
  if (key == ' ') {
    zoomOut();
  }
}


void zoomIn (float x_pos, float y_pos) {

  float magnification = 3.0;
  
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

void zoomOut () {
  float magnification = 3.0;
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
