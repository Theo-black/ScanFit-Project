"""
BMI Calculator with Size Recommendation
Calculates BMI and recommends clothing sizes based on gender, height, and weight.
"""

class BMICalculator:
    def __init__(self, gender, height_cm, weight_kg):
        # Initialize user attributes and placeholders for computed values
        self.gender = gender
        self.height_cm = height_cm
        self.weight_kg = weight_kg
        self.bmi = 0
        self.category = ""
        self.recommended_size = ""

    def calculate_bmi(self):
        """Convert height to meters and calculate BMI value."""
        height_m = self.height_cm / 100
        self.bmi = self.weight_kg / (height_m ** 2)
        return round(self.bmi, 2)

    def get_bmi_category(self):
        """Classify BMI into standard health categories."""
        if self.bmi < 18.5:
            self.category = "Underweight"
        elif self.bmi < 25:
            self.category = "Normal weight"
        elif self.bmi < 30:
            self.category = "Overweight"
        else:
            self.category = "Obese"
        return self.category

    def recommend_size(self):
        """
        Determine clothing size based on BMI (primary) and height (small adjustment),
        using the same mapping as the PHP bmi_calculator.php.
        """

        # Base size from BMI only (matches the size chart):
        # XS: BMI < 20
        # S : 20–22
        # M : 22–25
        # L : 25–28
        # XL: 28–30
        # XXL: > 30
        if self.bmi < 20:
            self.recommended_size = 'XS'
        elif self.bmi < 22:
            self.recommended_size = 'S'
        elif self.bmi < 25:
            self.recommended_size = 'M'
        elif self.bmi < 28:
            self.recommended_size = 'L'
        elif self.bmi < 30:
            self.recommended_size = 'XL'
        else:
            self.recommended_size = 'XXL'

        # Optional: adjust based on height
        # Very short users: nudge one size down if not already XS
        if self.height_cm < 160 and self.recommended_size != 'XS':
            map_down = {
                'S': 'XS',
                'M': 'S',
                'L': 'M',
                'XL': 'L',
                'XXL': 'XL',
            }
            self.recommended_size = map_down.get(self.recommended_size,
                                                 self.recommended_size)

        # Very tall users: nudge one size up if not already XXL
        if self.height_cm > 190 and self.recommended_size != 'XXL':
            map_up = {
                'XS': 'S',
                'S': 'M',
                'M': 'L',
                'L': 'XL',
                'XL': 'XXL',
            }
            self.recommended_size = map_up.get(self.recommended_size,
                                               self.recommended_size)

        return self.recommended_size

    def get_full_report(self):
        """Run full pipeline: calculate BMI, classify, and choose size."""
        self.calculate_bmi()
        self.get_bmi_category()
        self.recommend_size()

        return {
            'gender': self.gender,
            'height_cm': self.height_cm,
            'weight_kg': self.weight_kg,
            'bmi': round(self.bmi, 2),
            'category': self.category,
            'recommended_size': self.recommended_size
        }


def main():
    print("=" * 50)
    print("BMI CALCULATOR & SIZE RECOMMENDATION")
    print("=" * 50)
    print()

    # Collect input from the user
    gender = input("Enter gender (Male/Female): ").strip()
    height_cm = float(input("Enter height in cm: "))
    weight_kg = float(input("Enter weight in kg: "))

    print()

    calculator = BMICalculator(gender, height_cm, weight_kg)
    report = calculator.get_full_report()

    print("=" * 50)
    print("RESULTS")
    print("=" * 50)
    print(f"Gender: {report['gender']}")
    print(f"Height: {report['height_cm']} cm")
    print(f"Weight: {report['weight_kg']} kg")
    print(f"BMI: {report['bmi']}")
    print(f"Category: {report['category']}")
    print(f"Recommended Size: {report['recommended_size']}")
    print("=" * 50)
    print()
    print(
        f"Based on your measurements, we recommend size "
        f"{report['recommended_size']} for the best fit in our "
        f"{report['gender']} collection."
    )
    print()


if __name__ == "__main__":
    main()
