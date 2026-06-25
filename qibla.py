#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Qibla Direction Calculator for Iran

A production-grade, single-file Python application for calculating the Qibla direction
with the highest mathematically achievable precision using GeographicLib's Karney
Geodesic Algorithm and the WGS84 ellipsoid.

This software is designed to meet enterprise-quality standards comparable to systems
used by NASA, ESA, Trimble, Garmin, ESRI, and Google Maps.

Author: Principal Geodesy Engineer & Senior Python Software Architect
License: MIT
Version: 1.0.0
"""

from __future__ import annotations

import logging
import sys
from dataclasses import dataclass, field
from enum import Enum
from typing import Final, Optional, Tuple

from geographiclib.geodesic import Geodesic


# =============================================================================
# CONSTANTS
# =============================================================================

# WGS84 Ellipsoid Parameters (used internally by GeographicLib)
# These are the standard parameters defined by the World Geodetic System 1984
WGS84_A: Final[float] = 6378137.0  # Semi-major axis in meters
WGS84_F: Final[float] = 1 / 298.257223563  # Flattening

# Kaaba Coordinates (Masjid al-Haram, Mecca, Saudi Arabia)
# Source: High-precision GPS survey data
# These constants can be easily modified if more precise measurements become available
KAABA_LATITUDE: Final[float] = 21.422487  # Decimal degrees
KAABA_LONGITUDE: Final[float] = 39.826206  # Decimal degrees

# Iran Geographic Bounds (for validation purposes)
# Approximate bounding box for Iran
IRAN_LAT_MIN: Final[float] = 25.0
IRAN_LAT_MAX: Final[float] = 40.0
IRAN_LON_MIN: Final[float] = 44.0
IRAN_LON_MAX: Final[float] = 64.0

# Numerical Precision Constants
OUTPUT_PRECISION: Final[int] = 15  # Decimal places for output
FLOAT_TOLERANCE: Final[float] = 1e-15  # General floating-point tolerance
ANGLE_CLAMP_MIN: Final[float] = -180.0
ANGLE_CLAMP_MAX: Final[float] = 360.0


# =============================================================================
# CUSTOM EXCEPTIONS
# =============================================================================


class QiblaCalculatorError(Exception):
    """Base exception for Qibla calculator errors."""

    pass


class CoordinateValidationError(QiblaCalculatorError):
    """Exception raised when coordinate validation fails."""

    pass


class GeodesicCalculationError(QiblaCalculatorError):
    """Exception raised when geodesic calculation fails."""

    pass


class InputParsingError(QiblaCalculatorError):
    """Exception raised when input parsing fails."""

    pass


# =============================================================================
# ENUMERATIONS
# =============================================================================


class Hemisphere(Enum):
    """Enumeration for hemispheres."""

    NORTH = "N"
    SOUTH = "S"
    EAST = "E"
    WEST = "W"


# =============================================================================
# DATA CLASSES
# =============================================================================


@dataclass(frozen=True)
class Coordinate:
    """
    Represents a geographic coordinate with latitude and longitude.

    Attributes:
        latitude: Latitude in decimal degrees (-90 to 90).
        longitude: Longitude in decimal degrees (-180 to 180).
    """

    latitude: float
    longitude: float

    def __post_init__(self) -> None:
        """Validate coordinates after initialization."""
        object.__setattr__(self, "latitude", self._clamp_latitude(self.latitude))
        object.__setattr__(self, "longitude", self._normalize_longitude(self.longitude))

    @staticmethod
    def _clamp_latitude(lat: float) -> float:
        """
        Clamp latitude to valid range [-90, 90].

        Args:
            lat: Latitude value to clamp.

        Returns:
            Clamped latitude value.

        Raises:
            CoordinateValidationError: If latitude is NaN or infinite.
        """
        if lat != lat:  # NaN check
            raise CoordinateValidationError("Latitude cannot be NaN")
        if lat == float("inf") or lat == float("-inf"):
            raise CoordinateValidationError("Latitude cannot be infinite")
        if lat < -90.0 or lat > 90.0:
            raise CoordinateValidationError(
                f"Latitude must be between -90 and 90 degrees. Got: {lat}"
            )
        return max(-90.0, min(90.0, lat))

    @staticmethod
    def _normalize_longitude(lon: float) -> float:
        """
        Normalize longitude to range [-180, 180].

        Args:
            lon: Longitude value to normalize.

        Returns:
            Normalized longitude value.

        Raises:
            CoordinateValidationError: If longitude is NaN or infinite.
        """
        if lon != lon:  # NaN check
            raise CoordinateValidationError("Longitude cannot be NaN")
        if lon == float("inf") or lon == float("-inf"):
            raise CoordinateValidationError("Longitude cannot be infinite")

        # Normalize to [-180, 180]
        while lon > 180.0:
            lon -= 360.0
        while lon < -180.0:
            lon += 360.0

        return lon

    def to_tuple(self) -> Tuple[float, float]:
        """Convert coordinate to tuple."""
        return (self.latitude, self.longitude)


@dataclass(frozen=True)
class GeodesicResult:
    """
    Represents the result of a geodesic calculation.

    Attributes:
        distance: Distance between two points in meters.
        forward_azimuth: Forward azimuth (bearing) from start to end in degrees.
        reverse_azimuth: Reverse azimuth (bearing) from end to start in degrees.
    """

    distance: float
    forward_azimuth: float
    reverse_azimuth: float


@dataclass
class QiblaResult:
    """
    Represents the complete Qibla calculation result.

    Attributes:
        observer_coordinate: The observer's geographic coordinate.
        kaaba_coordinate: The Kaaba's geographic coordinate.
        distance_meters: Distance to Kaaba in meters.
        forward_azimuth: Forward azimuth from observer to Kaaba in degrees.
        qibla_from_south_west: Qibla angle measured from True South toward True West.
        back_azimuth: Back azimuth from Kaaba to observer in degrees.
    """

    observer_coordinate: Coordinate
    kaaba_coordinate: Coordinate
    distance_meters: float
    forward_azimuth: float
    qibla_from_south_west: float
    back_azimuth: float


# =============================================================================
# VALIDATOR CLASSES
# =============================================================================


class CoordinateValidator:
    """
    Validates geographic coordinates for Iran region.

    This class provides comprehensive validation for input coordinates,
    ensuring they fall within acceptable ranges and are properly formatted.
    """

    def __init__(
        self,
        lat_min: float = IRAN_LAT_MIN,
        lat_max: float = IRAN_LAT_MAX,
        lon_min: float = IRAN_LON_MIN,
        lon_max: float = IRAN_LON_MAX,
    ) -> None:
        """
        Initialize the coordinate validator with regional bounds.

        Args:
            lat_min: Minimum latitude for the region.
            lat_max: Maximum latitude for the region.
            lon_min: Minimum longitude for the region.
            lon_max: Maximum longitude for the region.
        """
        self._lat_min: float = lat_min
        self._lat_max: float = lat_max
        self._lon_min: float = lon_min
        self._lon_max: float = lon_max

    def validate_decimal_degrees(
        self, latitude: float, longitude: float
    ) -> Coordinate:
        """
        Validate latitude and longitude in decimal degrees format.

        Args:
            latitude: Latitude value in decimal degrees.
            longitude: Longitude value in decimal degrees.

        Returns:
            A validated Coordinate object.

        Raises:
            CoordinateValidationError: If validation fails.
        """
        # Check for None
        if latitude is None:
            raise CoordinateValidationError("Latitude cannot be None")
        if longitude is None:
            raise CoordinateValidationError("Longitude cannot be None")

        # Convert to float (handles string inputs that are numeric)
        try:
            lat_float = float(latitude)
            lon_float = float(longitude)
        except (ValueError, TypeError) as e:
            raise CoordinateValidationError(
                f"Latitude and longitude must be numeric values. Error: {e}"
            )

        # Check for NaN
        if lat_float != lat_float:
            raise CoordinateValidationError("Latitude cannot be NaN")
        if lon_float != lon_float:
            raise CoordinateValidationError("Longitude cannot be NaN")

        # Check for infinity
        if lat_float == float("inf") or lat_float == float("-inf"):
            raise CoordinateValidationError("Latitude cannot be infinite")
        if lon_float == float("inf") or lon_float == float("-inf"):
            raise CoordinateValidationError("Longitude cannot be infinite")

        # Validate latitude range
        if lat_float < -90.0 or lat_float > 90.0:
            raise CoordinateValidationError(
                f"Latitude must be between -90 and 90 degrees. Got: {lat_float}"
            )

        # Validate longitude range
        if lon_float < -180.0 or lon_float > 180.0:
            raise CoordinateValidationError(
                f"Longitude must be between -180 and 180 degrees. Got: {lon_float}"
            )

        # Validate Iran-specific bounds (warning only, not rejection)
        self._check_iran_bounds(lat_float, lon_float)

        return Coordinate(latitude=lat_float, longitude=lon_float)

    def _check_iran_bounds(self, lat: float, lon: float) -> None:
        """
        Check if coordinates are within Iran's approximate bounds.

        Args:
            lat: Latitude value.
            lon: Longitude value.

        Note:
            This method logs a warning if coordinates are outside Iran bounds
            but does not reject them.
        """
        logger = logging.getLogger(__name__)
        if not (self._lat_min <= lat <= self._lat_max):
            logger.warning(
                f"Latitude {lat}° is outside Iran's typical bounds "
                f"({self._lat_min}° to {self._lat_max}°)"
            )
        if not (self._lon_min <= lon <= self._lon_max):
            logger.warning(
                f"Longitude {lon}° is outside Iran's typical bounds "
                f"({self._lon_min}° to {self._lon_max}°)"
            )

    def parse_and_validate(self, lat_str: str, lon_str: str) -> Coordinate:
        """
        Parse string inputs and validate coordinates.

        Args:
            lat_str: Latitude as a string.
            lon_str: Longitude as a string.

        Returns:
            A validated Coordinate object.

        Raises:
            InputParsingError: If parsing fails.
            CoordinateValidationError: If validation fails.
        """
        # Handle empty strings
        if not lat_str or not lat_str.strip():
            raise InputParsingError("Latitude string is empty")
        if not lon_str or not lon_str.strip():
            raise InputParsingError("Longitude string is empty")

        # Strip whitespace
        lat_clean = lat_str.strip()
        lon_clean = lon_str.strip()

        # Check for Unicode issues (non-ASCII digits)
        # Convert common Unicode digit variants to ASCII
        lat_clean = self._normalize_unicode_digits(lat_clean)
        lon_clean = self._normalize_unicode_digits(lon_clean)

        # Parse to float
        try:
            lat_value = float(lat_clean)
            lon_value = float(lon_clean)
        except ValueError as e:
            raise InputParsingError(
                f"Failed to parse coordinates. Latitude='{lat_clean}', "
                f"Longitude='{lon_clean}'. Error: {e}"
            )

        return self.validate_decimal_degrees(lat_value, lon_value)

    @staticmethod
    def _normalize_unicode_digits(s: str) -> str:
        """
        Normalize Unicode digit characters to ASCII digits.

        Args:
            s: Input string potentially containing Unicode digits.

        Returns:
            String with ASCII digits only.
        """
        # Common Unicode digit mappings (Persian/Arabic digits)
        unicode_digits = {
            "۰": "0",
            "۱": "1",
            "۲": "2",
            "۳": "3",
            "۴": "4",
            "۵": "5",
            "۶": "6",
            "۷": "7",
            "۸": "8",
            "۹": "9",
            "٠": "0",
            "١": "1",
            "٢": "2",
            "٣": "3",
            "٤": "4",
            "٥": "5",
            "٦": "6",
            "٧": "7",
            "٨": "8",
            "٩": "9",
        }

        result = []
        for char in s:
            result.append(unicode_digits.get(char, char))

        return "".join(result)


# =============================================================================
# GEODESIC ENGINE
# =============================================================================


class GeodesicEngine:
    """
    High-precision geodesic calculations using GeographicLib's Karney algorithm.

    This class wraps GeographicLib to provide accurate geodesic calculations
    on the WGS84 ellipsoid. It uses Karney's algorithms which provide
    nanometer-level accuracy.

    References:
        Karney, C. F. F. (2013). Algorithms for geodesics. J Geod, 87, 43-55.
        https://doi.org/10.1007/s00190-012-0578-z
    """

    def __init__(self) -> None:
        """Initialize the geodesic engine with WGS84 ellipsoid parameters."""
        # GeographicLib's Geodesic class uses WGS84 by default
        # a = 6378137 m, f = 1/298.257223563
        self._geodesic: Geodesic = Geodesic.WGS84

    def calculate_inverse(
        self,
        lat1: float,
        lon1: float,
        lat2: float,
        lon2: float,
    ) -> GeodesicResult:
        """
        Solve the inverse geodesic problem.

        Given two points on the ellipsoid, calculate the distance and azimuths
        between them using Karney's algorithm.

        Args:
            lat1: Latitude of point 1 in degrees.
            lon1: Longitude of point 1 in degrees.
            lat2: Latitude of point 2 in degrees.
            lon2: Longitude of point 2 in degrees.

        Returns:
            GeodesicResult containing distance, forward azimuth, and reverse azimuth.

        Raises:
            GeodesicCalculationError: If the calculation fails.
        """
        try:
            # GeographicLib.Inverse returns a dictionary with results
            # s12: distance in meters
            # azi1: forward azimuth in degrees
            # azi2: reverse azimuth in degrees
            result = self._geodesic.Inverse(lat1, lon1, lat2, lon2)

            # Extract relevant values
            distance = result["s12"]
            forward_azimuth = result["azi1"]
            reverse_azimuth = result["azi2"]

            # Validate results
            if distance != distance:  # NaN check
                raise GeodesicCalculationError("Distance calculation resulted in NaN")

            if forward_azimuth != forward_azimuth:  # NaN check
                raise GeodesicCalculationError("Forward azimuth calculation resulted in NaN")

            if reverse_azimuth != reverse_azimuth:  # NaN check
                raise GeodesicCalculationError("Reverse azimuth calculation resulted in NaN")

            return GeodesicResult(
                distance=distance,
                forward_azimuth=self._normalize_angle(forward_azimuth),
                reverse_azimuth=self._normalize_angle(reverse_azimuth),
            )

        except Exception as e:
            raise GeodesicCalculationError(
                f"Geodesic calculation failed: {e}"
            ) from e

    @staticmethod
    def _normalize_angle(angle: float) -> float:
        """
        Normalize angle to [0, 360) range.

        Args:
            angle: Angle in degrees.

        Returns:
            Normalized angle in degrees.
        """
        angle = angle % 360.0
        if angle < 0.0:
            angle += 360.0
        return angle


# =============================================================================
# KAABA REFERENCE
# =============================================================================


class Kaaba:
    """
    Represents the Kaaba reference point in Mecca.

    This class stores the authoritative coordinates of the Kaaba and provides
    methods to access them. The coordinates can be updated if more precise
    measurements become available.
    """

    # Class-level constants for Kaaba coordinates
    _LATITUDE: Final[float] = KAABA_LATITUDE
    _LONGITUDE: Final[float] = KAABA_LONGITUDE

    def __init__(
        self,
        latitude: Optional[float] = None,
        longitude: Optional[float] = None,
    ) -> None:
        """
        Initialize the Kaaba reference point.

        Args:
            latitude: Override latitude in decimal degrees. If None, uses default.
            longitude: Override longitude in decimal degrees. If None, uses default.
        """
        self._latitude: float = latitude if latitude is not None else self._LATITUDE
        self._longitude: float = (
            longitude if longitude is not None else self._LONGITUDE
        )

    @property
    def latitude(self) -> float:
        """Get the Kaaba's latitude."""
        return self._latitude

    @property
    def longitude(self) -> float:
        """Get the Kaaba's longitude."""
        return self._longitude

    @property
    def coordinate(self) -> Coordinate:
        """Get the Kaaba's coordinate as a Coordinate object."""
        return Coordinate(latitude=self._latitude, longitude=self._longitude)

    @classmethod
    def get_default_coordinate(cls) -> Coordinate:
        """
        Get the default Kaaba coordinate.

        Returns:
            Coordinate object with default Kaaba coordinates.
        """
        return Coordinate(latitude=cls._LATITUDE, longitude=cls._LONGITUDE)


# =============================================================================
# FORMATTER
# =============================================================================


class Formatter:
    """
    Formats calculation results for display.

    Provides consistent formatting with high precision output suitable
    for professional geodesy applications.
    """

    def __init__(self, precision: int = OUTPUT_PRECISION) -> None:
        """
        Initialize the formatter.

        Args:
            precision: Number of decimal places for output.
        """
        self._precision: int = precision

    def format_coordinate(self, value: float, label: str) -> str:
        """
        Format a coordinate value.

        Args:
            value: Coordinate value in decimal degrees.
            label: Label for the coordinate.

        Returns:
            Formatted string.
        """
        return f"{label} :\n{value:.{self._precision}f}"

    def format_distance(self, distance: float) -> str:
        """
        Format a distance value.

        Args:
            distance: Distance in meters.

        Returns:
            Formatted string with unit.
        """
        return f"Distance :\n{distance:.{self._precision}f} m"

    def format_azimuth(self, azimuth: float, label: str) -> str:
        """
        Format an azimuth/bearing value.

        Args:
            azimuth: Azimuth in degrees.
            label: Label for the azimuth.

        Returns:
            Formatted string with degree symbol.
        """
        return f"{label} :\n{azimuth:.{self._precision}f}°"

    def format_qibla_angle(self, angle: float) -> str:
        """
        Format the Qibla angle from South toward West.

        Args:
            angle: Qibla angle in degrees.

        Returns:
            Formatted string.
        """
        return f"Qibla From South Toward West :\n{angle:.{self._precision}f}°"

    def format_result(self, result: QiblaResult) -> str:
        """
        Format a complete Qibla calculation result.

        Args:
            result: QiblaResult object.

        Returns:
            Formatted multi-line string with all result values.
        """
        lines = [
            "-" * 40,
            self.format_coordinate(
                result.observer_coordinate.latitude, "Latitude"
            ),
            self.format_coordinate(
                result.observer_coordinate.longitude, "Longitude"
            ),
            self.format_distance(result.distance_meters),
            self.format_azimuth(result.forward_azimuth, "Forward Azimuth"),
            self.format_qibla_angle(result.qibla_from_south_west),
            self.format_azimuth(result.back_azimuth, "Back Azimuth"),
            "-" * 40,
        ]
        return "\n".join(lines)


# =============================================================================
# QIBLA CALCULATOR
# =============================================================================


class QiblaCalculator:
    """
    Calculates the Qibla direction with maximum precision.

    This class implements the core Qibla calculation logic using high-precision
    geodesic algorithms. It calculates the Qibla angle measured from True South
    toward True West, which is the conventional representation used in Iran.

    Mathematical Background:
    -----------------------
    The Qibla direction is the initial bearing (forward azimuth) from an observer's
    location to the Kaaba in Mecca. In Iran, which is northeast of Mecca, the Qibla
    direction is typically expressed as an angle from South toward West.

    The relationship between Forward Azimuth (FA) and South-West Qibla Angle (SWQA):
    - Forward Azimuth is measured clockwise from True North (0° to 360°)
    - For Iran, FA is typically in the range 180° to 270° (Southwest quadrant)
    - SWQA = FA - 180° (angle from South toward West)

    Example:
    - If Forward Azimuth = 217.524875621348°
    - Then Qibla from South toward West = 217.524875621348° - 180° = 37.524875621348°
    """

    def __init__(
        self,
        geodesic_engine: Optional[GeodesicEngine] = None,
        kaaba: Optional[Kaaba] = None,
        validator: Optional[CoordinateValidator] = None,
    ) -> None:
        """
        Initialize the Qibla calculator.

        Args:
            geodesic_engine: GeodesicEngine instance. Creates default if None.
            kaaba: Kaaba reference point. Creates default if None.
            validator: CoordinateValidator instance. Creates default if None.
        """
        self._geodesic_engine: GeodesicEngine = (
            geodesic_engine if geodesic_engine is not None else GeodesicEngine()
        )
        self._kaaba: Kaaba = kaaba if kaaba is not None else Kaaba()
        self._validator: CoordinateValidator = (
            validator if validator is not None else CoordinateValidator()
        )
        self._formatter: Formatter = Formatter(precision=OUTPUT_PRECISION)

    def calculate(self, latitude: float, longitude: float) -> QiblaResult:
        """
        Calculate the Qibla direction for a given location.

        Args:
            latitude: Observer's latitude in decimal degrees.
            longitude: Observer's longitude in decimal degrees.

        Returns:
            QiblaResult containing all calculation results.

        Raises:
            CoordinateValidationError: If input coordinates are invalid.
            GeodesicCalculationError: If geodesic calculation fails.
        """
        # Validate input coordinates
        observer_coord = self._validator.validate_decimal_degrees(latitude, longitude)

        # Get Kaaba coordinate
        kaaba_coord = self._kaaba.coordinate

        # Perform geodesic calculation
        geodesic_result = self._geodesic_engine.calculate_inverse(
            lat1=observer_coord.latitude,
            lon1=observer_coord.longitude,
            lat2=kaaba_coord.latitude,
            lon2=kaaba_coord.longitude,
        )

        # Calculate Qibla angle from South toward West
        # For Iran (northeast of Kaaba), forward azimuth is in Southwest quadrant
        # Qibla from South toward West = Forward Azimuth - 180°
        forward_azimuth = geodesic_result.forward_azimuth

        # Calculate the South-West angle
        # The forward azimuth from Iran to Kaaba will be between 180° and 270°
        # (i.e., in the Southwest direction from the observer's perspective)
        qibla_from_south_west = self._calculate_south_west_angle(forward_azimuth)

        return QiblaResult(
            observer_coordinate=observer_coord,
            kaaba_coordinate=kaaba_coord,
            distance_meters=geodesic_result.distance,
            forward_azimuth=forward_azimuth,
            qibla_from_south_west=qibla_from_south_west,
            back_azimuth=geodesic_result.reverse_azimuth,
        )

    def _calculate_south_west_angle(self, forward_azimuth: float) -> float:
        """
        Calculate the Qibla angle measured from True South toward True West.

        Args:
            forward_azimuth: Forward azimuth from observer to Kaaba in degrees.

        Returns:
            Qibla angle from South toward West in degrees.

        Note:
            In Iran, the Kaaba is to the Southwest, so the forward azimuth
            will be between 180° and 270°. The angle from South toward West
            is simply: forward_azimuth - 180°
        """
        # Ensure forward azimuth is normalized
        fa_normalized = forward_azimuth % 360.0

        # For Iran, the Qibla is in the Southwest quadrant (180° to 270°)
        # Angle from South (180°) toward West (270°)
        south_west_angle = fa_normalized - 180.0

        # Clamp to handle any numerical edge cases
        if south_west_angle < 0.0:
            south_west_angle += 360.0

        # For Iran specifically, the angle should be between 0° and 90°
        # (Southwest quadrant relative to South)
        # If it's not, we may need to adjust based on hemisphere
        if south_west_angle > 180.0:
            # This would indicate a different quadrant; handle gracefully
            south_west_angle = south_west_angle % 180.0

        return south_west_angle

    def calculate_from_strings(self, lat_str: str, lon_str: str) -> QiblaResult:
        """
        Calculate Qibla direction from string inputs.

        Args:
            lat_str: Latitude as a string.
            lon_str: Longitude as a string.

        Returns:
            QiblaResult containing all calculation results.

        Raises:
            InputParsingError: If string parsing fails.
            CoordinateValidationError: If validation fails.
            GeodesicCalculationError: If geodesic calculation fails.
        """
        coord = self._validator.parse_and_validate(lat_str, lon_str)
        return self.calculate(coord.latitude, coord.longitude)

    def format_result(self, result: QiblaResult) -> str:
        """
        Format a Qibla result for display.

        Args:
            result: QiblaResult object.

        Returns:
            Formatted string.
        """
        return self._formatter.format_result(result)


# =============================================================================
# CLI INTERFACE
# =============================================================================


class CLI:
    """
    Command-line interface for the Qibla calculator.

    Provides a user-friendly interface for calculating Qibla directions
    from command-line arguments or interactive input.
    """

    def __init__(self, calculator: Optional[QiblaCalculator] = None) -> None:
        """
        Initialize the CLI.

        Args:
            calculator: QiblaCalculator instance. Creates default if None.
        """
        self._calculator: QiblaCalculator = (
            calculator if calculator is not None else QiblaCalculator()
        )
        self._logger: logging.Logger = logging.getLogger(__name__)

    def run(self, args: Optional[list[str]] = None) -> int:
        """
        Run the CLI application.

        Args:
            args: Command-line arguments. Uses sys.argv if None.

        Returns:
            Exit code (0 for success, non-zero for error).
        """
        if args is None:
            args = sys.argv[1:]

        try:
            if len(args) >= 2:
                # Command-line mode
                lat_str = args[0]
                lon_str = args[1]
                return self._process_coordinates(lat_str, lon_str)
            elif len(args) == 1 and args[0] in ["-h", "--help"]:
                self._print_help()
                return 0
            else:
                # Interactive mode
                return self._interactive_mode()

        except KeyboardInterrupt:
            print("\nOperation cancelled by user.")
            return 130
        except Exception as e:
            self._logger.error(f"Unexpected error: {e}")
            print(f"Error: {e}")
            return 1

    def _process_coordinates(self, lat_str: str, lon_str: str) -> int:
        """
        Process coordinates and display results.

        Args:
            lat_str: Latitude string.
            lon_str: Longitude string.

        Returns:
            Exit code.
        """
        try:
            result = self._calculator.calculate_from_strings(lat_str, lon_str)
            print(self._calculator.format_result(result))
            return 0

        except InputParsingError as e:
            print(f"Input Error: {e}")
            return 1
        except CoordinateValidationError as e:
            print(f"Validation Error: {e}")
            return 1
        except GeodesicCalculationError as e:
            print(f"Calculation Error: {e}")
            return 1

    def _interactive_mode(self) -> int:
        """
        Run in interactive mode.

        Returns:
            Exit code.
        """
        print("=" * 50)
        print("Qibla Direction Calculator for Iran")
        print("=" * 50)
        print()
        print("Enter coordinates in decimal degrees.")
        print("Example: 35.6892 51.3890 (Tehran)")
        print()

        try:
            lat_input = input("Enter latitude: ")
            lon_input = input("Enter longitude: ")

            return self._process_coordinates(lat_input, lon_input)

        except EOFError:
            print("\nNo input provided.")
            return 1

    def _print_help(self) -> None:
        """Print help message."""
        help_text = """
Qibla Direction Calculator for Iran
====================================

Usage:
    python qibla.py <latitude> <longitude>
    python qibla.py --help
    python qibla.py  (interactive mode)

Arguments:
    latitude   - Latitude in decimal degrees (e.g., 35.6892)
    longitude  - Longitude in decimal degrees (e.g., 51.3890)

Output:
    The calculator outputs:
    - Latitude and Longitude
    - Distance to Kaaba (meters)
    - Forward Azimuth (degrees from True North)
    - Qibla From South Toward West (degrees)
    - Back Azimuth (degrees)

Example:
    python qibla.py 35.6892 51.3890

    This calculates the Qibla direction for Tehran, Iran.

Notes:
    - Coordinates should be within Iran's bounds for intended use.
    - Results are calculated using GeographicLib's Karney algorithm
      with WGS84 ellipsoid for maximum precision.
    - Output precision is 15 decimal places.
"""
        print(help_text)


# =============================================================================
# APPLICATION
# =============================================================================


class Application:
    """
    Main application class.

    Orchestrates all components and provides the entry point for the application.
    """

    def __init__(self) -> None:
        """Initialize the application."""
        self._setup_logging()
        self._calculator: QiblaCalculator = QiblaCalculator()
        self._cli: CLI = CLI(calculator=self._calculator)

    def _setup_logging(self) -> None:
        """Configure logging for the application."""
        logging.basicConfig(
            level=logging.INFO,
            format="%(asctime)s - %(name)s - %(levelname)s - %(message)s",
            handlers=[logging.StreamHandler(sys.stderr)],
        )

    def run(self, args: Optional[list[str]] = None) -> int:
        """
        Run the application.

        Args:
            args: Command-line arguments.

        Returns:
            Exit code.
        """
        return self._cli.run(args)


# =============================================================================
# VERIFICATION UTILITIES
# =============================================================================


class VerificationUtils:
    """
    Utilities for verifying calculation correctness.

    Provides methods to cross-check results against known benchmarks
    and validate numerical accuracy.
    """

    @staticmethod
    def verify_geographiclib_result(
        lat1: float,
        lon1: float,
        lat2: float,
        lon2: float,
        expected_azimuth: Optional[float] = None,
        tolerance: float = 1e-12,
    ) -> Tuple[bool, str]:
        """
        Verify a GeographicLib calculation result.

        Args:
            lat1: Latitude of point 1.
            lon1: Longitude of point 1.
            lat2: Latitude of point 2.
            lon2: Longitude of point 2.
            expected_azimuth: Expected azimuth value (if known).
            tolerance: Maximum acceptable error in degrees.

        Returns:
            Tuple of (success, message).
        """
        geodesic = Geodesic.WGS84
        result = geodesic.Inverse(lat1, lon1, lat2, lon2)

        messages = []
        success = True

        # Check for NaN values
        if result["s12"] != result["s12"]:
            return False, "Distance is NaN"
        if result["azi1"] != result["azi1"]:
            return False, "Forward azimuth is NaN"
        if result["azi2"] != result["azi2"]:
            return False, "Reverse azimuth is NaN"

        # Normalize azimuths to [0, 360) for validation
        azi1_normalized = result["azi1"] % 360.0
        azi2_normalized = result["azi2"] % 360.0

        # Check against expected value if provided
        if expected_azimuth is not None:
            actual_azimuth = azi1_normalized
            error = abs(actual_azimuth - expected_azimuth)
            if error > tolerance:
                success = False
                messages.append(
                    f"Azimuth error exceeds tolerance: {error} > {tolerance}"
                )
            else:
                messages.append(f"Azimuth verification passed (error: {error})")

        # Verify azimuth normalization
        if not (0 <= azi1_normalized < 360):
            success = False
            messages.append(f"Forward azimuth out of range: {azi1_normalized}")

        if not (0 <= azi2_normalized < 360):
            success = False
            messages.append(f"Reverse azimuth out of range: {azi2_normalized}")

        # Verify distance is positive
        if result["s12"] < 0:
            success = False
            messages.append(f"Negative distance: {result['s12']}")

        return success, "; ".join(messages) if messages else "Verification passed"

    @staticmethod
    def run_test_cases() -> None:
        """Run internal test cases for verification."""
        logger = logging.getLogger(__name__)
        logger.info("Running verification test cases...")

        test_cases = [
            # (lat1, lon1, lat2, lon2, description)
            (35.6892, 51.3890, 21.422487, 39.826206, "Tehran to Kaaba"),
            (32.4279, 53.6880, 21.422487, 39.826206, "Yazd to Kaaba"),
            (36.2605, 59.6168, 21.422487, 39.826206, "Mashhad to Kaaba"),
            (29.5918, 52.4322, 21.422487, 39.826206, "Shiraz to Kaaba"),
            (38.0217, 46.2847, 21.422487, 39.826206, "Tabriz to Kaaba"),
        ]

        geodesic = Geodesic.WGS84

        for lat1, lon1, lat2, lon2, description in test_cases:
            result = geodesic.Inverse(lat1, lon1, lat2, lon2)

            # Verify no NaN values
            assert result["s12"] == result["s12"], f"NaN distance in {description}"
            assert result["azi1"] == result["azi1"], f"NaN azimuth in {description}"
            assert result["azi2"] == result["azi2"], f"NaN reverse azimuth in {description}"

            # Verify reasonable distance (should be > 0 and < 20000 km for Iran-Mecca)
            assert 0 < result["s12"] < 20000000, f"Invalid distance in {description}"

            # Verify azimuth in valid range
            assert 0 <= result["azi1"] < 360, f"Invalid forward azimuth in {description}"
            assert 0 <= result["azi2"] < 360, f"Invalid reverse azimuth in {description}"

            logger.info(f"✓ {description}: Distance={result['s12']:.3f}m, "
                       f"Azimuth={result['azi1']:.12f}°")

        logger.info("All verification test cases passed.")


# =============================================================================
# MODULE ENTRY POINT
# =============================================================================

# Configure module-level logger
logger = logging.getLogger(__name__)


def main() -> int:
    """
    Main entry point for the Qibla calculator application.

    Returns:
        Exit code (0 for success, non-zero for error).
    """
    app = Application()
    return app.run()


if __name__ == "__main__":
    sys.exit(main())
