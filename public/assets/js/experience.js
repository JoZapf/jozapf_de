function updateExperienceYears() {
  const startDate = new Date('1999-01-01');
  const currentDate = new Date();
  
  let years = currentDate.getFullYear() - startDate.getFullYear();
  const monthDiff = currentDate.getMonth() - startDate.getMonth();
  const dayDiff = currentDate.getDate() - startDate.getDate();
  
  if (monthDiff < 0 || (monthDiff === 0 && dayDiff < 0)) {
    years--;
  }
  
  // Direkte HTML-Ausgabe
  document.write(years);
}