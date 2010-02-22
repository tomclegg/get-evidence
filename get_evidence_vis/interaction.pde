void mousePressed() {
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
